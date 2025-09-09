/**
 * GroupModal Component
 * 
 * Specialized modal for CRUD operations on product/ingredient groups
 * Handles Create, Edit, and Delete operations with proper validation
 */

import React, { useState, useEffect } from 'react';
import { ExclamationTriangleIcon, PlusIcon, PencilIcon } from '@heroicons/react/24/outline';
import Modal from './Modal.jsx';
import { Button } from '../atoms';
import { DEFAULT_THEME } from '../../../config/theme.js';
import api from '../../../services/api.js';
import ProductEditForm from '../../ProductEditForm.jsx';

const GroupModal = ({ 
  isOpen = false,
  onClose = () => {},
  mode = 'create', // 'create' | 'edit' | 'delete'
  groupType = 'product', // 'product' | 'ingredient'
  initialData = null, // For edit mode
  branches = [],
  categories = [], // For product form
  groups = [], // Available groups for selection
  onSuccess = () => {}, // Called after successful operation
  strings = {}
}) => {
  const theme = DEFAULT_THEME;

  // Form state
  const [formData, setFormData] = useState({
    name: '',
    status: 'active',
    branch_id: 0
  });
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState({});

  // Initialize form data for edit mode
  useEffect(() => {
    if (mode === 'edit' && initialData) {
      setFormData({
        name: initialData.name || '',
        status: initialData.status || 'active',
        branch_id: initialData.branch_id || 0
      });
    } else if (mode === 'create') {
      setFormData({
        name: '',
        status: 'active',
        branch_id: 0
      });
    }
    setErrors({});
  }, [mode, initialData, isOpen]);

  // Validation
  const validateForm = () => {
    const newErrors = {};
    
    if (!formData.name.trim()) {
      newErrors.name = strings.name_required || 'שם הקבוצה נדרש';
    }
    
    if (formData.name.trim().length < 2) {
      newErrors.name = strings.name_too_short || 'שם הקבוצה חייב להכיל לפחות 2 תווים';
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  // Handle ProductEditForm save
  const handleProductFormSave = async (productFormData) => {
    setLoading(true);
    
    try {
      let result;
      
      // Transform ProductEditForm data to API format
      const apiData = {
        name: productFormData.name,
        description: productFormData.description,
        price: parseFloat(productFormData.price) || 0,
        discount_price: productFormData.discountPrice ? parseFloat(productFormData.discountPrice) : null,
        category_id: productFormData.useNewCategory ? null : productFormData.categoryId,
        new_category: productFormData.useNewCategory ? productFormData.newCategory : null,
        groups: productFormData.selectedGroups.map(g => g.id),
        image: productFormData.image?.file || null
      };
      
      if (mode === 'create') {
        if (groupType === 'product') {
          result = await api.createProductGroup(apiData);
        } else {
          result = await api.createIngredientGroup(apiData);
        }
      } else if (mode === 'edit') {
        if (groupType === 'product') {
          result = await api.updateProductGroup(initialData.id, apiData);
        } else {
          result = await api.updateIngredientGroup(initialData.id, apiData);
        }
      }
      
      onSuccess(result);
      onClose();
    } catch (error) {
      setErrors({ 
        submit: error.message || strings.operation_failed || 'הפעולה נכשלה' 
      });
    } finally {
      setLoading(false);
    }
  };

  // Handle delete operation
  const handleDelete = async () => {
    setLoading(true);
    
    try {
      if (groupType === 'product') {
        await api.deleteProductGroup(initialData.id);
      } else {
        await api.deleteIngredientGroup(initialData.id);
      }
      
      onSuccess();
      onClose();
    } catch (error) {
      setErrors({ 
        submit: error.message || strings.delete_failed || 'מחיקה נכשלה' 
      });
    } finally {
      setLoading(false);
    }
  };

  // Get modal configuration based on mode
  const getModalConfig = () => {
    switch (mode) {
      case 'create':
        return {
          title: `${strings.create_new || 'צור חדש'} ${groupType === 'product' ? strings.product_group || 'קבוצת מוצרים' : strings.ingredient_group || 'קבוצת מרכיבים'}`,
          icon: PlusIcon,
          confirmText: strings.create || 'צור',
          confirmVariant: 'primary'
        };
      case 'edit':
        return {
          title: `${strings.edit || 'ערוך'} ${groupType === 'product' ? strings.product_group || 'קבוצת מוצרים' : strings.ingredient_group || 'קבוצת מרכיבים'}`,
          icon: PencilIcon,
          confirmText: strings.save || 'שמור',
          confirmVariant: 'primary'
        };
      case 'delete':
        return {
          title: `${strings.delete || 'מחק'} ${groupType === 'product' ? strings.product_group || 'קבוצת מוצרים' : strings.ingredient_group || 'קבוצת מרכיבים'}`,
          icon: ExclamationTriangleIcon,
          confirmText: strings.delete || 'מחק',
          confirmVariant: 'error'
        };
      default:
        return {};
    }
  };

  const config = getModalConfig();

  return (
    <>
      {mode === 'delete' ? (
        <Modal
          isOpen={isOpen}
          onClose={onClose}
          title={config.title}
          size="sm"
        >
          {/* Delete Confirmation */}
          <div className="text-center">
          <div className="flex justify-center mb-4">
            <div 
              className="w-12 h-12 rounded-full flex items-center justify-center"
              style={{ backgroundColor: `${theme.danger_color}20` }}
            >
              <ExclamationTriangleIcon 
                className="w-6 h-6" 
                style={{ color: theme.danger_color }}
              />
            </div>
          </div>
          
          <p className="text-lg mb-2" style={{ color: theme.text_primary }}>
            {strings.confirm_delete_title || 'האם אתה בטוח?'}
          </p>
          
          <p className="mb-6" style={{ color: theme.text_secondary }}>
            {strings.confirm_delete_message || 'פעולה זו תמחק את'}{' '}
            <strong>"{initialData?.name}"</strong>{' '}
            {strings.confirm_delete_suffix || 'ולא ניתן לבטל אותה.'}
          </p>

          {errors.submit && (
            <div 
              className="p-3 mb-4 rounded-lg text-sm text-center"
              style={{ 
                backgroundColor: theme.error_bg,
                border: `1px solid ${theme.error_border}`,
                color: theme.danger_color
              }}
            >
              {errors.submit}
            </div>
          )}

          <div className="flex gap-3 justify-center">
            <Button
              variant="outline"
              onClick={onClose}
              disabled={loading}
            >
              {strings.cancel || 'ביטול'}
            </Button>
            <Button
              variant="error"
              onClick={handleDelete}
              disabled={loading}
            >
              {loading ? (strings.deleting || 'מוחק...') : (strings.delete || 'מחק')}
            </Button>
          </div>
          </div>
        </Modal>
      ) : (
        // Create/Edit Form - Use ProductEditForm inside Modal
        <Modal
          isOpen={isOpen}
          onClose={onClose}
          title={config.title}
          size="xl"
          showCloseButton={true}
        >
          <ProductEditForm
            initialData={mode === 'edit' ? initialData : null}
            onSave={handleProductFormSave}
            onCancel={onClose}
            categories={categories}
            groups={groups}
            strings={strings}
            isInModal={true}
          />
        </Modal>
      )}
    </>
  );
};

export default GroupModal;