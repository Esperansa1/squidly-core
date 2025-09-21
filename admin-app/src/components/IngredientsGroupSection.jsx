import React, { useState, useEffect, useCallback, useMemo } from 'react';
import api from '../services/api.js';
import { DataTable, SearchBar, Card } from './ui';
import IngredientsGroupModal from './ui/IngredientsGroupModal.jsx';

const IngredientsGroupSection = ({ strings }) => {
  const [ingredientGroups, setIngredientGroups] = useState([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [showModal, setShowModal] = useState(false);
  const [editingGroup, setEditingGroup] = useState(null);
  const [selectedItem, setSelectedItem] = useState(null);

  const loadIngredientGroups = useCallback(async (filters = {}) => {
    setIsLoading(true);
    try {
      const response = await api.getIngredientGroups(filters);
      const groups = response.data || response || [];
      setIngredientGroups(Array.isArray(groups) ? groups : []);
    } catch (error) {
      console.error('Error loading ingredient groups:', error);
      setIngredientGroups([]);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadIngredientGroups();
  }, [loadIngredientGroups]);

  const handleSearch = useCallback((term) => {
    setSearchTerm(term);
    const filters = term ? { search: term } : {};
    loadIngredientGroups(filters);
  }, [loadIngredientGroups]);

  const handleCreate = useCallback(() => {
    setEditingGroup(null);
    setShowModal(true);
  }, []);

  const handleEdit = useCallback((group) => {
    if (!group) return;
    setEditingGroup(group);
    setShowModal(true);
  }, []);

  const handleDelete = useCallback(async (group) => {
    if (!group || !group.id) return;

    const groupName = group.name || 'this group';
    if (!window.confirm(strings.confirmDelete?.replace('%s', groupName) || `Delete ${groupName}?`)) {
      return;
    }

    try {
      await api.deleteIngredientGroup(group.id);
      loadIngredientGroups();
    } catch (error) {
      console.error('Error deleting ingredient group:', error);
      // Show error message - will be handled by Toast component later
    }
  }, [strings.confirmDelete, loadIngredientGroups]);

  const handleModalSave = useCallback(async (groupData) => {
    try {
      if (editingGroup) {
        await api.updateIngredientGroup(editingGroup.id, groupData);
      } else {
        await api.createIngredientGroup(groupData);
      }
      setShowModal(false);
      setEditingGroup(null);
      loadIngredientGroups();
    } catch (error) {
      console.error('Error saving ingredient group:', error);
      throw error; // Let modal handle the error
    }
  }, [editingGroup, loadIngredientGroups]);

  const columns = useMemo(() => [
    {
      key: 'name',
      label: strings.name || 'Name',
      className: 'text-right flex-1',
      render: (group) => {
        if (!group) return <span>-</span>;
        return <span className="font-medium text-gray-900">{group.name || ''}</span>;
      }
    },
    {
      key: 'description',
      label: strings.description || 'Description',
      className: 'text-right flex-1',
      render: (group) => {
        if (!group) return <span>-</span>;
        if (!group.description) {
          return <span className="text-gray-400 italic">{strings.noDescription || 'No description'}</span>;
        }
        const truncated = group.description.length > 50
          ? group.description.substring(0, 50) + '...'
          : group.description;
        return <span className="text-gray-700">{truncated}</span>;
      }
    },
    {
      key: 'item_count',
      label: strings.itemCount || 'Items',
      className: 'text-center w-24',
      render: (group) => {
        if (!group) return <span>0</span>;
        const itemCount = Array.isArray(group.group_item_ids) ? group.group_item_ids.length : 0;
        return (
          <span className="inline-flex items-center justify-center px-2 py-1 text-xs font-medium text-blue-800 bg-blue-100 rounded-full">
            {itemCount}
          </span>
        );
      }
    }
  ], [strings]);

  // Filter data based on search term
  const filteredData = useMemo(() => {
    if (!searchTerm.trim()) {
      return ingredientGroups;
    }
    return ingredientGroups.filter(group =>
      group && group.name && group.name.toLowerCase().includes(searchTerm.toLowerCase())
    );
  }, [ingredientGroups, searchTerm]);

  return (
    <>
      <Card className="h-full flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <h2 className="text-xl font-semibold text-gray-900">{strings.ingredientGroups || 'קבוצות'}</h2>
          <button
            onClick={handleCreate}
            className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium"
          >
            {strings.addIngredientGroup || 'הוסף קבוצה'}
          </button>
        </div>

        {/* Search */}
        <div className="p-6 border-b border-gray-200">
          <SearchBar
            value={searchTerm}
            onChange={setSearchTerm}
            placeholder={strings.searchIngredientGroups || 'חפש קבוצות...'}
          />
        </div>

        {/* Data Table */}
        <div className="flex-1 p-6">
          {isLoading ? (
            <div className="flex items-center justify-center h-32">
              <div className="text-gray-500">טוען...</div>
            </div>
          ) : filteredData.length === 0 ? (
            <div className="flex items-center justify-center h-32">
              <div className="text-gray-500">{strings.noIngredientGroups || 'לא נמצאו קבוצות'}</div>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={filteredData}
              selectedId={selectedItem}
              onSelectionChange={setSelectedItem}
              onEdit={handleEdit}
              onDelete={handleDelete}
            />
          )}
        </div>
      </Card>

      <IngredientsGroupModal
        isOpen={showModal}
        onClose={() => setShowModal(false)}
        onSave={handleModalSave}
        group={editingGroup}
        strings={strings}
      />
    </>
  );
};

export default IngredientsGroupSection;