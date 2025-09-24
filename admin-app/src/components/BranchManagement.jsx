import React, { useState, useEffect } from 'react';
import api from '../services/api.js';
import { DEFAULT_THEME } from '../config/theme.js';
import {
  Card,
  SearchBar,
  DataTable,
  ConfirmationModal,
  Toast,
  BranchModal,
  ActionButtons
} from './ui';

const BranchManagement = () => {
  const theme = DEFAULT_THEME;

  // State
  const [branches, setBranches] = useState([]);
  const [selectedBranch, setSelectedBranch] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [branchToReselect, setBranchToReselect] = useState(null);

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

  // Reselect branch after data reload
  useEffect(() => {
    if (branchToReselect && branches.length > 0) {
      const updatedBranch = branches.find(branch => branch.id === branchToReselect);
      if (updatedBranch) {
        setSelectedBranch(updatedBranch);
      }
      setBranchToReselect(null);
    }
  }, [branches, branchToReselect]);

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
        <div className="font-medium" style={{ color: theme.text_primary }}>{branch.name}</div>
      )
    },
    {
      key: 'phone',
      title: 'טלפון',
      width: '150px',
      sortable: true,
      render: (fieldValue, branch) => (
        <div style={{ color: theme.text_secondary, direction: 'ltr', textAlign: 'left' }}>{branch.phone}</div>
      )
    },
    {
      key: 'city',
      title: 'עיר',
      width: '120px',
      sortable: true,
      render: (fieldValue, branch) => (
        <div style={{ color: theme.text_secondary }}>{branch.city}</div>
      )
    },
    {
      key: 'address',
      title: 'כתובת',
      width: '200px',
      sortable: true,
      render: (fieldValue, branch) => (
        <div style={{ color: theme.text_secondary }}>{branch.address}</div>
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
        <div className="text-sm" style={{ color: theme.text_secondary }}>
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

      // If editing, mark the branch to be reselected with fresh data after reload
      if (editingBranch) {
        setBranchToReselect(editingBranch.id);
      }

      await loadBranches(); // Reload data

      setShowBranchModal(false);
      setEditingBranch(null);
      setShowToast(true);
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
      {/* Content */}
      <div className="flex-1 p-6 overflow-hidden">
        <Card className="h-full flex flex-col" padding="none">
          <div className="flex-shrink-0 p-4 border-b border-gray-200">
            {/* Title */}
            <div className="mb-4">
              <h2 className="text-lg text-neutral-800 font-bold">ניהול סניפים</h2>
            </div>

            {/* Search Bar and Action Buttons */}
            <div className="flex items-center gap-4">
              {/* Search Bar - Flexible width */}
              <div className="flex-1 mr-4">
                <SearchBar
                  value={searchTerm}
                  onChange={setSearchTerm}
                  placeholder="חיפוש סניפים..."
                />
              </div>

              {/* Action Buttons */}
              <ActionButtons
                onAdd={handleCreateBranch}
                onEdit={handleEditBranch}
                onDelete={handleDeleteBranch}
                editDisabled={!selectedBranch}
                deleteDisabled={!selectedBranch}
                addTooltip="הוסף סניף"
                editTooltip="ערוך סניף"
                deleteTooltip="מחק סניף"
              />
            </div>
          </div>

          {/* Data Table */}
          <div className="flex-1 p-8 pt-6 min-h-0">
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