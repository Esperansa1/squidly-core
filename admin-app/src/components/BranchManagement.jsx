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
  const [selectedBranchId, setSelectedBranchId] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Pagination state
  const [totalBranches, setTotalBranches] = useState(0);
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage, setItemsPerPage] = useState(10);
  const [tableLoading, setTableLoading] = useState(false);
  const [hasInitiallyLoaded, setHasInitiallyLoaded] = useState(false);

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
    loadBranches(false).then(() => {
      setHasInitiallyLoaded(true);
    });
  }, []);

  // Reset to page 1 when search changes
  useEffect(() => {
    setCurrentPage(1);
    setHasInitiallyLoaded(false);
  }, [searchTerm]);

  // Full reload when search changes
  useEffect(() => {
    loadBranches(false).then(() => {
      setHasInitiallyLoaded(true);
    });
  }, [searchTerm]);

  // Table-only reload when pagination changes
  useEffect(() => {
    if (hasInitiallyLoaded) {
      loadBranches(true);
    }
  }, [currentPage, itemsPerPage, hasInitiallyLoaded]);

  const loadBranches = async (isTableOnly = false) => {
    try {
      if (isTableOnly) {
        setTableLoading(true);
      } else {
        setLoading(true);
      }
      setError(null);

      // Build filters with pagination
      const offset = (currentPage - 1) * itemsPerPage;
      const filters = {
        offset,
        per_page: itemsPerPage
      };

      // Add search filter if present
      if (searchTerm.trim()) {
        filters.search = searchTerm.trim();
      }

      const response = await api.getBranches(filters, true);  // Include pagination headers

      setBranches(response.data);
      setTotalBranches(response.total);
    } catch (error) {
      console.error('Failed to load branches:', error);
      setError(error.message || 'שגיאה בטעינת הסניפים');
    } finally {
      if (isTableOnly) {
        setTableLoading(false);
      } else {
        setLoading(false);
      }
    }
  };

  // Branches are already filtered by backend based on searchTerm
  const filteredBranches = branches;

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
    if (selectedBranchId) {
      // Find fresh branch data from current branches array (DataSection pattern)
      const branch = filteredBranches.find(b => b.id === selectedBranchId);
      if (branch) {
        setEditingBranch(branch);
        setShowBranchModal(true);
      }
    }
  };

  const handleDeleteBranch = () => {
    if (selectedBranchId) {
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
    if (!selectedBranchId) return;

    try {
      setIsDeleting(true);
      await api.deleteBranch(selectedBranchId);
      setShowDeleteModal(false);
      setSelectedBranchId(null);
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
    // Follow DataSection pattern - extract ID from item object
    const branchId = branch ? branch.id : null;
    setSelectedBranchId(branchId);
  };

  return (
    <div className="h-full flex flex-col" dir="rtl">
      {/* Content */}
      <div className="flex-1 p-6 overflow-auto">
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
                editDisabled={!selectedBranchId}
                deleteDisabled={!selectedBranchId}
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
              selectedId={selectedBranchId}
              onSelectionChange={handleSelectionChange}
              loading={tableLoading}
              error={error}
              emptyMessage="אין סניפים להצגה"
              showPagination={true}
              currentPage={currentPage}
              itemsPerPage={itemsPerPage}
              totalItems={totalBranches}
              onPageChange={setCurrentPage}
              onItemsPerPageChange={setItemsPerPage}
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
        message={`האם אתה בטוח שברצונך למחוק את הסניף "${filteredBranches.find(b => b.id === selectedBranchId)?.name}"?`}
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