import React, { useState, useEffect } from 'react';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/react/24/outline';
import api from '../services/api.js';
import { DEFAULT_THEME } from '../config/theme.js';
import { Card, SearchBar, DataTable, ConfirmationModal, Toast, BranchModal } from './ui';

const BranchManagement = () => {
  const theme = DEFAULT_THEME;

  // State
  const [branches, setBranches] = useState([]);
  const [selectedBranch, setSelectedBranch] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Modal states
  const [showBranchModal, setShowBranchModal] = useState(false);
  const [editingBranch, setEditingBranch] = useState(null);
  const [isSaving, setIsSaving] = useState(false);

  // Delete modal state
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);

  // Toast state
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState('');

  // Load branches on component mount
  useEffect(() => {
    loadBranches();
  }, []);

  const loadBranches = async () => {
    try {
      setLoading(true);
      setError(null);
      const branchesData = await api.getBranches();
      console.log('Branches API response:', branchesData); // Debug log
      setBranches(branchesData);
    } catch (error) {
      console.error('Failed to load branches:', error);
      setError(error.message || 'שגיאה בטעינת הסניפים');
    } finally {
      setLoading(false);
    }
  };

  // Filter branches based on search term
  const filteredBranches = branches.filter(branch =>
    branch.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    branch.city.toLowerCase().includes(searchTerm.toLowerCase()) ||
    branch.phone.includes(searchTerm)
  );

  // Format activity times for display
  const formatActivityTimes = (activityTimes) => {
    if (!activityTimes || Object.keys(activityTimes).length === 0) {
      return 'לא הוגדר';
    }

    const dayTranslations = {
      'SUNDAY': 'א',
      'MONDAY': 'ב',
      'TUESDAY': 'ג',
      'WEDNESDAY': 'ד',
      'THURSDAY': 'ה',
      'FRIDAY': 'ו',
      'SATURDAY': 'ש'
    };

    const activeDays = Object.keys(activityTimes)
      .filter(day => activityTimes[day] && activityTimes[day].length > 0)
      .map(day => dayTranslations[day] || day)
      .join(', ');

    return activeDays || 'לא הוגדר';
  };

  // Table columns configuration
  const columns = [
    {
      key: 'selection',
      title: '',
      width: '60px',
      render: (branch) => null // Selection is handled by DataTable
    },
    {
      key: 'name',
      title: 'שם הסניף',
      width: '200px',
      sortable: true,
      render: (fieldValue, branch) => (
        <div className="font-medium text-gray-900">{branch.name}</div>
      )
    },
    {
      key: 'phone',
      title: 'טלפון',
      width: '150px',
      sortable: true,
      render: (fieldValue, branch) => (
        <div className="text-gray-700">{branch.phone}</div>
      )
    },
    {
      key: 'city',
      title: 'עיר',
      width: '120px',
      sortable: true,
      render: (fieldValue, branch) => (
        <div className="text-gray-700">{branch.city}</div>
      )
    },
    {
      key: 'address',
      title: 'כתובת',
      width: '200px',
      sortable: true,
      render: (fieldValue, branch) => (
        <div className="text-gray-700">{branch.address}</div>
      )
    },
    {
      key: 'status',
      title: 'סטטוס',
      width: '100px',
      sortable: true,
      render: (fieldValue, branch) => (
        <span
          className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${
            branch.is_open
              ? 'bg-green-100 text-green-800'
              : 'bg-red-100 text-red-800'
          }`}
        >
          {branch.is_open ? 'פעיל' : 'סגור'}
        </span>
      )
    },
    {
      key: 'activity_times',
      title: 'ימי פעילות',
      width: '150px',
      render: (fieldValue, branch) => (
        <div className="text-gray-700 text-sm">
          {formatActivityTimes(branch.activity_times)}
        </div>
      )
    }
  ];

  // Action handlers
  const handleCreateBranch = () => {
    setEditingBranch(null);
    setShowBranchModal(true);
  };

  const handleEditBranch = () => {
    if (selectedBranch) {
      setEditingBranch(selectedBranch);
      setShowBranchModal(true);
    }
  };

  const handleDeleteBranch = () => {
    if (selectedBranch) {
      setShowDeleteModal(true);
    }
  };

  const handleSaveBranch = async (branchData) => {
    try {
      setIsSaving(true);

      if (editingBranch) {
        // Update existing branch
        await api.updateBranch(editingBranch.id, branchData);
        setToastMessage('הסניף עודכן בהצלחה');
      } else {
        // Create new branch
        await api.createBranch(branchData);
        setToastMessage('הסניף נוצר בהצלחה');
      }

      setShowBranchModal(false);
      setEditingBranch(null);
      setShowToast(true);
      await loadBranches(); // Reload data
    } catch (error) {
      console.error('Failed to save branch:', error);
      setToastMessage(error.message || 'שגיאה בשמירת הסניף');
      setShowToast(true);
    } finally {
      setIsSaving(false);
    }
  };

  const confirmDelete = async () => {
    if (!selectedBranch) return;

    try {
      setIsDeleting(true);
      await api.deleteBranch(selectedBranch.id);
      setShowDeleteModal(false);
      setSelectedBranch(null);
      setToastMessage('הסניף נמחק בהצלחה');
      setShowToast(true);
      await loadBranches(); // Reload data
    } catch (error) {
      console.error('Failed to delete branch:', error);
      setToastMessage(error.message || 'שגיאה במחיקת הסניף');
      setShowToast(true);
    } finally {
      setIsDeleting(false);
    }
  };

  const handleSelectionChange = (branch) => {
    setSelectedBranch(branch);
  };

  return (
    <div className="h-full flex flex-col" dir="rtl">
      {/* Header */}
      <div className="flex-shrink-0 p-6 border-b border-gray-200">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">ניהול סניפים</h1>
            <p className="text-gray-600 mt-1">נהל את כל הסניפים של המסעדה שלך</p>
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="flex-1 p-6 overflow-hidden">
        <Card className="h-full flex flex-col">
          {/* Toolbar */}
          <div className="flex-shrink-0 p-4 border-b border-gray-200">
            <div className="flex items-center justify-between">
              {/* Search */}
              <div className="flex-1 max-w-md">
                <SearchBar
                  value={searchTerm}
                  onChange={setSearchTerm}
                  placeholder="חיפוש סניפים..."
                />
              </div>

              {/* Action Buttons */}
              <div className="flex items-center gap-2">
                <button
                  onClick={handleDeleteBranch}
                  disabled={!selectedBranch}
                  className="flex items-center justify-center w-10 h-10 text-white bg-red-600 rounded-lg transition-colors hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <TrashIcon className="w-4 h-4" />
                </button>

                <button
                  onClick={handleEditBranch}
                  disabled={!selectedBranch}
                  className="flex items-center justify-center w-10 h-10 text-gray-700 bg-gray-100 rounded-lg transition-colors hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <PencilIcon className="w-4 h-4" />
                </button>

                <button
                  onClick={handleCreateBranch}
                  className="flex items-center justify-center w-10 h-10 text-white rounded-lg transition-colors hover:opacity-90"
                  style={{ backgroundColor: theme.primary_color }}
                >
                  <PlusIcon className="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          {/* Data Table */}
          <div className="flex-1 overflow-hidden">
            <DataTable
              columns={columns}
              data={filteredBranches}
              selectedId={selectedBranch?.id}
              onSelectionChange={handleSelectionChange}
              loading={loading}
              error={error}
              emptyMessage="אין סניפים להצגה"
            />
          </div>
        </Card>
      </div>

      {/* Branch Modal */}
      <BranchModal
        isOpen={showBranchModal}
        onClose={() => {
          setShowBranchModal(false);
          setEditingBranch(null);
        }}
        onSave={handleSaveBranch}
        editingBranch={editingBranch}
        isSaving={isSaving}
      />

      {/* Delete Confirmation Modal */}
      <ConfirmationModal
        isOpen={showDeleteModal}
        onClose={() => setShowDeleteModal(false)}
        onConfirm={confirmDelete}
        title="מחיקת סניף"
        message={`האם אתה בטוח שברצונך למחוק את הסניף "${selectedBranch?.name}"?`}
        confirmText="מחק"
        cancelText="ביטול"
        isLoading={isDeleting}
        variant="danger"
      />

      {/* Toast Notification */}
      <Toast
        show={showToast}
        message={toastMessage}
        onClose={() => setShowToast(false)}
        duration={3000}
      />
    </div>
  );
};

export default BranchManagement;