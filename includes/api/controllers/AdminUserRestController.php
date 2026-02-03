<?php
/**
 * Admin User REST Controller
 *
 * Handles REST API endpoints for admin user management.
 *
 * @package SquidlyCore
 * @since 1.0.0
 */

namespace SquidlyCore\Api\Controllers;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use SquidlyCore\Admin\RoleManager;

if (!defined('ABSPATH')) {
    exit;
}

class AdminUserRestController extends WP_REST_Controller {
    /**
     * Constructor
     */
    public function __construct() {
        $this->namespace = 'squidly/v1';
        $this->rest_base = 'admin-users';
    }

    /**
     * Register routes
     */
    public function register_routes() {
        // List and create users
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'get_items_permissions_check'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'create_item_permissions_check'],
                'args' => $this->get_create_params(),
            ],
        ]);

        // Get, update, delete single user
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'get_item_permissions_check'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'update_item_permissions_check'],
                'args' => $this->get_update_params(),
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'delete_item_permissions_check'],
            ],
        ]);

        // Current user endpoints
        register_rest_route($this->namespace, '/' . $this->rest_base . '/me', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_current_user'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_current_user'],
                'permission_callback' => '__return_true',
                'args' => $this->get_update_current_user_params(),
            ],
        ]);

        // Avatar upload
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+|me)/avatar', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'upload_avatar'],
            'permission_callback' => [$this, 'upload_avatar_permissions_check'],
        ]);
    }

    /**
     * Get list of admin users
     */
    public function get_items($request) {
        $per_page = (int) ($request->get_param('per_page') ?: 10);
        $offset = (int) ($request->get_param('offset') ?: 0);
        $search = $request->get_param('search') ?: '';
        $role = $request->get_param('role') ?: '';

        $args = [
            'number' => $per_page,
            'offset' => $offset,
            'orderby' => 'registered',
            'order' => 'DESC',
        ];

        // Filter by restaurant roles
        $allowed_roles = array_keys(RoleManager::get_allowed_roles());
        if ($role && in_array($role, $allowed_roles)) {
            $args['role__in'] = [$role];
        } else {
            $args['role__in'] = $allowed_roles;
        }

        if ($search) {
            $args['search'] = '*' . sanitize_text_field($search) . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        $user_query = new \WP_User_Query($args);
        $users = $user_query->get_results();
        $total = $user_query->get_total();

        $formatted_users = array_map([$this, 'format_user'], $users);

        $response = new WP_REST_Response($formatted_users);
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', ceil($total / $per_page));

        return $response;
    }

    /**
     * Get single user
     */
    public function get_item($request) {
        $user_id = (int) $request['id'];
        $user = get_user_by('id', $user_id);

        if (!$user) {
            return new WP_Error('user_not_found', 'User not found', ['status' => 404]);
        }

        return new WP_REST_Response($this->format_user($user));
    }

    /**
     * Create new user
     */
    public function create_item($request) {
        $username = sanitize_user($request->get_param('username'));
        $email = sanitize_email($request->get_param('email'));
        $password = $request->get_param('password');
        $role = sanitize_text_field($request->get_param('role'));
        $display_name = sanitize_text_field($request->get_param('display_name'));
        $first_name = sanitize_text_field($request->get_param('first_name') ?: '');
        $last_name = sanitize_text_field($request->get_param('last_name') ?: '');

        // Validate role
        if (!RoleManager::is_restaurant_role($role)) {
            return new WP_Error('invalid_role', 'Invalid role', ['status' => 400]);
        }

        // Check if username exists
        if (username_exists($username)) {
            return new WP_Error('username_exists', 'Username already exists', ['status' => 400]);
        }

        // Check if email exists
        if (email_exists($email)) {
            return new WP_Error('email_exists', 'Email already exists', ['status' => 400]);
        }

        // Create user
        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'role' => $role,
            'display_name' => $display_name,
            'first_name' => $first_name,
            'last_name' => $last_name,
        ]);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        $user = get_user_by('id', $user_id);
        return new WP_REST_Response($this->format_user($user), 201);
    }

    /**
     * Update user
     */
    public function update_item($request) {
        $user_id = (int) $request['id'];
        $user = get_user_by('id', $user_id);

        if (!$user) {
            return new WP_Error('user_not_found', 'User not found', ['status' => 404]);
        }

        $update_data = ['ID' => $user_id];

        if ($request->has_param('email')) {
            $email = sanitize_email($request->get_param('email'));
            if ($email !== $user->user_email && email_exists($email)) {
                return new WP_Error('email_exists', 'Email already exists', ['status' => 400]);
            }
            $update_data['user_email'] = $email;
        }

        if ($request->has_param('display_name')) {
            $update_data['display_name'] = sanitize_text_field($request->get_param('display_name'));
        }

        if ($request->has_param('first_name')) {
            $update_data['first_name'] = sanitize_text_field($request->get_param('first_name'));
        }

        if ($request->has_param('last_name')) {
            $update_data['last_name'] = sanitize_text_field($request->get_param('last_name'));
        }

        if ($request->has_param('role')) {
            $role = sanitize_text_field($request->get_param('role'));
            if (!RoleManager::is_restaurant_role($role)) {
                return new WP_Error('invalid_role', 'Invalid role', ['status' => 400]);
            }
            $update_data['role'] = $role;
        }

        if ($request->has_param('password')) {
            $update_data['user_pass'] = $request->get_param('password');
        }

        $result = wp_update_user($update_data);

        if (is_wp_error($result)) {
            return $result;
        }

        $updated_user = get_user_by('id', $user_id);
        return new WP_REST_Response($this->format_user($updated_user));
    }

    /**
     * Delete user
     */
    public function delete_item($request) {
        $user_id = (int) $request['id'];
        $current_user_id = get_current_user_id();

        // Prevent self-deletion
        if ($user_id === $current_user_id) {
            return new WP_Error('cannot_delete_self', 'You cannot delete your own account', ['status' => 403]);
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new WP_Error('user_not_found', 'User not found', ['status' => 404]);
        }

        require_once(ABSPATH . 'wp-admin/includes/user.php');
        $result = wp_delete_user($user_id);

        if (!$result) {
            return new WP_Error('delete_failed', 'Failed to delete user', ['status' => 500]);
        }

        return new WP_REST_Response(['deleted' => true], 200);
    }

    /**
     * Get current logged-in user
     */
    public function get_current_user($request) {
        $user = wp_get_current_user();

        if (!$user || $user->ID === 0) {
            return new WP_Error('not_logged_in', 'User not logged in', ['status' => 401]);
        }

        return new WP_REST_Response($this->format_user($user));
    }

    /**
     * Update current user profile
     */
    public function update_current_user($request) {
        $user = wp_get_current_user();

        if (!$user || $user->ID === 0) {
            return new WP_Error('not_logged_in', 'User not logged in', ['status' => 401]);
        }

        $update_data = ['ID' => $user->ID];

        if ($request->has_param('display_name')) {
            $update_data['display_name'] = sanitize_text_field($request->get_param('display_name'));
        }

        if ($request->has_param('first_name')) {
            $update_data['first_name'] = sanitize_text_field($request->get_param('first_name'));
        }

        if ($request->has_param('last_name')) {
            $update_data['last_name'] = sanitize_text_field($request->get_param('last_name'));
        }

        if ($request->has_param('email')) {
            $email = sanitize_email($request->get_param('email'));
            if ($email !== $user->user_email && email_exists($email)) {
                return new WP_Error('email_exists', 'Email already exists', ['status' => 400]);
            }
            $update_data['user_email'] = $email;
        }

        if ($request->has_param('password')) {
            $update_data['user_pass'] = $request->get_param('password');
        }

        $result = wp_update_user($update_data);

        if (is_wp_error($result)) {
            return $result;
        }

        $updated_user = get_user_by('id', $user->ID);
        return new WP_REST_Response($this->format_user($updated_user));
    }

    /**
     * Upload avatar
     */
    public function upload_avatar($request) {
        $user_id_param = $request['id'];
        $current_user_id = get_current_user_id();

        // Determine target user ID
        if ($user_id_param === 'me') {
            $user_id = $current_user_id;
        } else {
            $user_id = (int) $user_id_param;
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new WP_Error('user_not_found', 'User not found', ['status' => 404]);
        }

        $files = $request->get_file_params();
        if (empty($files['avatar'])) {
            return new WP_Error('no_file', 'No file uploaded', ['status' => 400]);
        }

        // Handle file upload
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('avatar', 0);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Save attachment ID to user meta
        update_user_meta($user_id, '_squidly_avatar_id', $attachment_id);

        $avatar_url = wp_get_attachment_url($attachment_id);

        return new WP_REST_Response([
            'avatar_url' => $avatar_url,
            'attachment_id' => $attachment_id,
        ]);
    }

    /**
     * Format user for API response
     */
    private function format_user($user) {
        // Get custom avatar or Gravatar
        $custom_avatar_id = get_user_meta($user->ID, '_squidly_avatar_id', true);
        if ($custom_avatar_id) {
            $avatar_url = wp_get_attachment_url($custom_avatar_id);
        } else {
            $avatar_url = get_avatar_url($user->ID, ['size' => 96]);
        }

        return [
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'role' => !empty($user->roles) ? $user->roles[0] : '',
            'avatar_url' => $avatar_url,
            'registered_date' => $user->user_registered,
        ];
    }

    /**
     * Permission checks
     */
    public function get_items_permissions_check($request) {
        return current_user_can('manage_options');
    }

    public function get_item_permissions_check($request) {
        return current_user_can('manage_options');
    }

    public function create_item_permissions_check($request) {
        return current_user_can('manage_options');
    }

    public function update_item_permissions_check($request) {
        return current_user_can('manage_options');
    }

    public function delete_item_permissions_check($request) {
        return current_user_can('manage_options');
    }

    public function upload_avatar_permissions_check($request) {
        $user_id_param = $request['id'];
        $current_user_id = get_current_user_id();

        // Users can always upload their own avatar
        if ($user_id_param === 'me' || (int) $user_id_param === $current_user_id) {
            return true;
        }

        // Otherwise require manage_options
        return current_user_can('manage_options');
    }

    /**
     * Get create parameters
     */
    private function get_create_params() {
        return [
            'username' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_user',
            ],
            'email' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
            ],
            'password' => [
                'required' => true,
                'type' => 'string',
            ],
            'role' => [
                'required' => true,
                'type' => 'string',
            ],
            'display_name' => [
                'required' => true,
                'type' => 'string',
            ],
            'first_name' => [
                'type' => 'string',
            ],
            'last_name' => [
                'type' => 'string',
            ],
        ];
    }

    /**
     * Get update parameters
     */
    private function get_update_params() {
        return [
            'email' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
            ],
            'display_name' => [
                'type' => 'string',
            ],
            'first_name' => [
                'type' => 'string',
            ],
            'last_name' => [
                'type' => 'string',
            ],
            'role' => [
                'type' => 'string',
            ],
            'password' => [
                'type' => 'string',
            ],
        ];
    }

    /**
     * Get update current user parameters
     */
    private function get_update_current_user_params() {
        return [
            'display_name' => [
                'type' => 'string',
            ],
            'first_name' => [
                'type' => 'string',
            ],
            'last_name' => [
                'type' => 'string',
            ],
            'email' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
            ],
            'password' => [
                'type' => 'string',
            ],
        ];
    }
}
