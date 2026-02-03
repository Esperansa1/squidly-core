import { useState, useEffect } from 'react';
import Card from '../ui/atoms/Card.jsx';
import SearchBar from '../ui/SearchBar.jsx';
import ActionButtons from '../ui/molecules/ActionButtons.jsx';
import DataTable from '../ui/DataTable.jsx';
import UserModal from '../ui/UserModal.jsx';
import ConfirmationModal from '../ui/ConfirmationModal.jsx';
import Toast from '../ui/Toast.jsx';
import api from '../../services/api.js';

const UserManagement = () => {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage, setItemsPerPage] = useState(10);
  const [totalUsers, setTotalUsers] = useState(0);
  const [selectedUserId, setSelectedUserId] = useState(null);
  const [showUserModal, setShowUserModal] = useState(false);
  const [editingUser, setEditingUser] = useState(null);
  const [userToDelete, setUserToDelete] = useState(null);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState('');
  const [toastType, setToastType] = useState('success');

  useEffect(() => {
    loadUsers();
  }, [currentPage, itemsPerPage, searchTerm]);

  const loadUsers = async () => {
    try {
      setLoading(true);
      const filters = {
        per_page: itemsPerPage,
        offset: (currentPage - 1) * itemsPerPage,
      };

      if (searchTerm) {
        filters.search = searchTerm;
      }

      const response = await api.getAdminUsers(filters, true);
      setUsers(response.data);
      setTotalUsers(response.total);
    } catch (error) {
      console.error('Error loading users:', error);
      displayToast('שגיאה בטעינת המשתמשים', 'error');
    } finally {
      setLoading(false);
    }
  };

  const handleAddUser = () => {
    setEditingUser(null);
    setShowUserModal(true);
  };

  const handleEditUser = () => {
    const user = users.find(u => u.id === selectedUserId);
    if (user) {
      setEditingUser(user);
      setShowUserModal(true);
    }
  };

  const handleSaveUser = async (userData) => {
    try {
      if (editingUser) {
        await api.updateAdminUser(editingUser.id, userData);
        displayToast('המשתמש עודכן בהצלחה');
      } else {
        await api.createAdminUser(userData);
        displayToast('המשתמש נוסף בהצלחה');
      }
      setShowUserModal(false);
      loadUsers();
    } catch (error) {
      console.error('Error saving user:', error);
      throw error;
    }
  };

  const handleDeleteClick = () => {
    const user = users.find(u => u.id === selectedUserId);
    if (user) {
      setUserToDelete(user);
      setShowDeleteModal(true);
    }
  };

  const handleConfirmDelete = async () => {
    if (!userToDelete) return;

    try {
      await api.deleteAdminUser(userToDelete.id);
      displayToast('המשתמש נמחק בהצלחה');
      setShowDeleteModal(false);
      setUserToDelete(null);
      setSelectedUserId(null);
      loadUsers();
    } catch (error) {
      console.error('Error deleting user:', error);
      const errorMessage = error.message || 'שגיאה במחיקת המשתמש';
      displayToast(errorMessage, 'error');
    }
  };

  const handleSelectionChange = (user) => {
    setSelectedUserId(user?.id || null);
  };

  const displayToast = (message, type = 'success') => {
    setToastMessage(message);
    setToastType(type);
    setShowToast(true);
  };

  const getRoleLabel = (role) => {
    const roleLabels = {
      'administrator': 'מנהל',
      'restaurant_manager': 'מנהל מסעדה',
      'restaurant_staff': 'צוות'
    };
    return roleLabels[role] || role;
  };

  const columns = [
    {
      key: 'avatar',
      title: 'תמונה',
      width: '80px',
      render: (value, user) => (
        <img
          src={user.avatar_url}
          alt={user.display_name}
          className="w-10 h-10 rounded-full object-cover ring-2 ring-gray-200"
        />
      )
    },
    {
      key: 'display_name',
      title: 'שם',
      width: '200px',
      sortable: true
    },
    {
      key: 'email',
      title: 'אימייל',
      width: '220px',
      sortable: true
    },
    {
      key: 'role',
      title: 'תפקיד',
      width: '150px',
      render: (role) => getRoleLabel(role)
    },
    {
      key: 'registered_date',
      title: 'תאריך הרשמה',
      width: '150px',
      render: (date) => {
        if (!date) return '-';
        const d = new Date(date);
        return d.toLocaleDateString('he-IL');
      }
    }
  ];

  return (
    <div className="h-full flex flex-col pt-6" dir="rtl">
      <Card className="flex-1 min-h-0 overflow-hidden flex flex-col" padding="none">
          {/* Header with Title */}
          <div className="flex-shrink-0 p-4 border-b border-gray-200">
            <div className="mb-4">
              <h2 className="text-lg text-neutral-800 font-bold">ניהול משתמשים</h2>
            </div>
            <div className="flex items-center justify-between gap-4">
              <SearchBar
                value={searchTerm}
                onChange={setSearchTerm}
                placeholder="חיפוש לפי שם או אימייל..."
              />
              <ActionButtons
                onAdd={handleAddUser}
                onEdit={handleEditUser}
                onDelete={handleDeleteClick}
                editDisabled={!selectedUserId}
                deleteDisabled={!selectedUserId}
                addTooltip="הוסף משתמש"
                editTooltip="ערוך משתמש"
                deleteTooltip="מחק משתמש"
              />
            </div>
          </div>

          {/* DataTable with built-in pagination */}
          <div className="flex-1 min-h-0">
            <DataTable
            columns={columns}
            data={users}
            selectedId={selectedUserId}
            onSelectionChange={handleSelectionChange}
            loading={loading}
            emptyMessage="לא נמצאו משתמשים"
            showPagination={true}
            currentPage={currentPage}
            itemsPerPage={itemsPerPage}
            totalItems={totalUsers}
            onPageChange={setCurrentPage}
            onItemsPerPageChange={(newValue) => {
              setItemsPerPage(newValue);
              setCurrentPage(1);
            }}
          />
        </div>
      </Card>

      {showUserModal && (
        <UserModal
          user={editingUser}
          onSave={handleSaveUser}
          onClose={() => setShowUserModal(false)}
        />
      )}

      {showDeleteModal && (
        <ConfirmationModal
          isOpen={showDeleteModal}
          title="מחיקת משתמש"
          message={`האם אתה בטוח שברצונך למחוק את המשתמש "${userToDelete?.display_name}"?`}
          confirmText="מחק"
          cancelText="ביטול"
          onConfirm={handleConfirmDelete}
          onCancel={() => {
            setShowDeleteModal(false);
            setUserToDelete(null);
          }}
          variant="danger"
        />
      )}

      {showToast && (
        <Toast
          message={toastMessage}
          type={toastType}
          onClose={() => setShowToast(false)}
        />
      )}
    </div>
  );
};

export default UserManagement;
