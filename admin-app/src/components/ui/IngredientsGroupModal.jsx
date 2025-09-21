import React, { useState, useEffect, useCallback } from 'react';
import { XMarkIcon } from '@heroicons/react/24/outline';
import { api } from '../../services/api.js';
import DropdownButton from './DropdownButton.jsx';

const IngredientsGroupModal = ({
  isOpen,
  onClose,
  onSave,
  group = null,
  strings = {}
}) => {
  const [formData, setFormData] = useState({
    name: '',
    description: '',
    group_item_ids: []
  });

  const [availableIngredients, setAvailableIngredients] = useState([]);
  const [selectedIngredients, setSelectedIngredients] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');

  // Load available ingredients when modal opens
  useEffect(() => {
    if (isOpen) {
      loadAvailableIngredients();
    }
  }, [isOpen]);

  // Populate form when editing existing group
  useEffect(() => {
    if (group) {
      setFormData({
        name: group.name || '',
        description: group.description || '',
        group_item_ids: group.group_item_ids || []
      });
    } else {
      setFormData({
        name: '',
        description: '',
        group_item_ids: []
      });
      setSelectedIngredients([]);
    }
  }, [group]);

  const loadAvailableIngredients = async () => {
    try {
      setIsLoading(true);
      const response = await api.getIngredients();
      setAvailableIngredients(response.data || []);
    } catch (error) {
      console.error('Error loading ingredients:', error);
      setError(strings.errorLoadingIngredients || 'Error loading ingredients');
    } finally {
      setIsLoading(false);
    }
  };

  const handleInputChange = useCallback((e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  }, []);

  const handleIngredientSelect = useCallback((ingredientId) => {
    const ingredient = availableIngredients.find(ing => ing.id === parseInt(ingredientId));
    if (ingredient && !selectedIngredients.find(ing => ing.id === ingredient.id)) {
      const newSelected = [...selectedIngredients, ingredient];
      setSelectedIngredients(newSelected);
      setFormData(prev => ({
        ...prev,
        group_item_ids: newSelected.map(ing => ing.id)
      }));
    }
  }, [availableIngredients, selectedIngredients]);

  const handleRemoveIngredient = useCallback((ingredientId) => {
    const newSelected = selectedIngredients.filter(ing => ing.id !== ingredientId);
    setSelectedIngredients(newSelected);
    setFormData(prev => ({
      ...prev,
      group_item_ids: newSelected.map(ing => ing.id)
    }));
  }, [selectedIngredients]);

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!formData.name.trim()) {
      setError(strings.nameRequired || 'Name is required');
      return;
    }

    try {
      setIsLoading(true);
      setError('');
      await onSave(formData);
    } catch (error) {
      console.error('Error saving ingredient group:', error);
      setError(error.message || strings.errorSaving || 'Error saving ingredient group');
    } finally {
      setIsLoading(false);
    }
  };

  const handleClose = useCallback(() => {
    setFormData({ name: '', description: '', group_item_ids: [] });
    setSelectedIngredients([]);
    setError('');
    onClose();
  }, [onClose]);

  // Get ingredients not yet selected
  const unselectedIngredients = availableIngredients.filter(
    ingredient => !selectedIngredients.find(selected => selected.id === ingredient.id)
  );

  const ingredientDropdownOptions = unselectedIngredients.map(ingredient => ({
    value: ingredient.id.toString(),
    label: ingredient.name
  }));

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div className="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
        {/* Header */}
        <div className="flex items-center justify-between border-b pb-4 mb-4">
          <h3 className="text-lg font-medium text-gray-900">
            {group ?
              (strings.editIngredientGroup || 'Edit Ingredient Group') :
              (strings.addIngredientGroup || 'Add Ingredient Group')
            }
          </h3>
          <button
            onClick={handleClose}
            className="text-gray-400 hover:text-gray-600 transition-colors"
            disabled={isLoading}
          >
            <XMarkIcon className="h-6 w-6" />
          </button>
        </div>

        {/* Error Message */}
        {error && (
          <div className="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
            {error}
          </div>
        )}

        {/* Form */}
        <form onSubmit={handleSubmit}>
          {/* Name Field */}
          <div className="mb-4">
            <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-2 text-right">
              {strings.name || 'Name'} *
            </label>
            <input
              type="text"
              id="name"
              name="name"
              value={formData.name}
              onChange={handleInputChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-right"
              placeholder={strings.enterGroupName || 'Enter group name...'}
              disabled={isLoading}
              required
            />
          </div>

          {/* Description Field */}
          <div className="mb-4">
            <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-2 text-right">
              {strings.description || 'Description'}
            </label>
            <textarea
              id="description"
              name="description"
              value={formData.description}
              onChange={handleInputChange}
              rows={3}
              className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-right"
              placeholder={strings.enterGroupDescription || 'Enter group description...'}
              disabled={isLoading}
            />
          </div>

          {/* Ingredients Selection */}
          <div className="mb-6">
            <label className="block text-sm font-medium text-gray-700 mb-2 text-right">
              {strings.ingredients || 'Ingredients'}
            </label>

            {/* Selected Ingredients */}
            {selectedIngredients.length > 0 && (
              <div className="mb-3">
                <div className="flex flex-wrap gap-2">
                  {selectedIngredients.map(ingredient => (
                    <span
                      key={ingredient.id}
                      className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800"
                    >
                      {ingredient.name}
                      <button
                        type="button"
                        onClick={() => handleRemoveIngredient(ingredient.id)}
                        className="ml-2 text-blue-600 hover:text-blue-800"
                        disabled={isLoading}
                      >
                        ×
                      </button>
                    </span>
                  ))}
                </div>
              </div>
            )}

            {/* Dropdown for adding ingredients */}
            <DropdownButton
              options={ingredientDropdownOptions}
              value=""
              onChange={handleIngredientSelect}
              placeholder={strings.selectIngredient || 'Select ingredient...'}
              disabled={isLoading || ingredientDropdownOptions.length === 0}
            />

            {ingredientDropdownOptions.length === 0 && !isLoading && (
              <p className="text-sm text-gray-500 mt-1 text-right">
                {strings.allIngredientsSelected || 'All ingredients have been selected'}
              </p>
            )}
          </div>

          {/* Form Actions */}
          <div className="flex justify-end space-x-3 pt-4 border-t">
            <button
              type="button"
              onClick={handleClose}
              className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 disabled:opacity-50"
              disabled={isLoading}
            >
              {strings.cancel || 'Cancel'}
            </button>
            <button
              type="submit"
              className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
              disabled={isLoading}
            >
              {isLoading ?
                (strings.saving || 'Saving...') :
                (strings.save || 'Save')
              }
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default IngredientsGroupModal;