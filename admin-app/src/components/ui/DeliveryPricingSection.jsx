import React, { useState, useEffect } from 'react';
import { PlusIcon, TrashIcon, ChevronDownIcon, ChevronUpIcon } from '@heroicons/react/24/outline';
import api from '../../services/api.js';
import { FormField, Button, IconButton, Select, Divider } from './index';

/**
 * DeliveryPricingSection - Manage advanced delivery pricing for a branch
 * Handles tiers, time surcharges, and loyalty discounts
 * Uses pre-created themed components for consistency
 */
const DeliveryPricingSection = ({ branchId, isOpen }) => {
  const [expanded, setExpanded] = useState(false);
  const [activeTab, setActiveTab] = useState('tiers'); // 'tiers', 'surcharges', 'discounts'

  // State for each pricing type
  const [tiers, setTiers] = useState([]);
  const [surcharges, setSurcharges] = useState([]);
  const [discounts, setDiscounts] = useState([]);

  // Loading states
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);

  // Form states for adding new items
  const [newTier, setNewTier] = useState({ min_order_value: '', delivery_fee: '' });
  const [newSurcharge, setNewSurcharge] = useState({
    day_of_week: '0',
    start_time: '',
    end_time: '',
    surcharge_amount: '',
    surcharge_type: 'fixed',
  });
  const [newDiscount, setNewDiscount] = useState({
    min_loyalty_points: '',
    discount_amount: '',
    discount_type: 'fixed',
  });

  const daysOfWeek = [
    { value: '0', label: 'ראשון' },
    { value: '1', label: 'שני' },
    { value: '2', label: 'שלישי' },
    { value: '3', label: 'רביעי' },
    { value: '4', label: 'חמישי' },
    { value: '5', label: 'שישי' },
    { value: '6', label: 'שבת' },
  ];

  const surchargeTypeOptions = [
    { value: 'fixed', label: 'סכום קבוע (₪)' },
    { value: 'percentage', label: 'אחוז (%)' },
  ];

  const discountTypeOptions = [
    { value: 'fixed', label: 'סכום קבוע (₪)' },
    { value: 'percentage', label: 'אחוז (%)' },
  ];

  // Load data when branch is selected and section is expanded
  useEffect(() => {
    if (branchId && isOpen && expanded) {
      loadAll();
    }
  }, [branchId, isOpen, expanded]);

  const loadAll = async () => {
    setLoading(true);
    try {
      const [tiersData, surchargesData, discountsData] = await Promise.all([
        api.getDeliveryTiers(branchId),
        api.getDeliveryTimeSurcharges(branchId),
        api.getDeliveryLoyaltyDiscounts(),
      ]);
      setTiers(tiersData || []);
      setSurcharges(surchargesData || []);
      setDiscounts(discountsData || []);
    } catch (error) {
      console.error('Error loading delivery pricing:', error);
    } finally {
      setLoading(false);
    }
  };

  // === TIERS ===
  const handleAddTier = async () => {
    if (!newTier.min_order_value || !newTier.delivery_fee) return;
    setSaving(true);
    try {
      await api.createDeliveryTier({
        branch_id: branchId,
        min_order_value: parseFloat(newTier.min_order_value),
        delivery_fee: parseFloat(newTier.delivery_fee),
      });
      setNewTier({ min_order_value: '', delivery_fee: '' });
      await loadAll();
    } catch (error) {
      console.error('Error creating tier:', error);
      alert('שגיאה ביצירת דרגת תמחור');
    } finally {
      setSaving(false);
    }
  };

  const handleDeleteTier = async (id) => {
    if (!confirm('למחוק דרגה זו?')) return;
    setSaving(true);
    try {
      await api.deleteDeliveryTier(id);
      await loadAll();
    } catch (error) {
      console.error('Error deleting tier:', error);
      alert('שגיאה במחיקת דרגה');
    } finally {
      setSaving(false);
    }
  };

  // === SURCHARGES ===
  const handleAddSurcharge = async () => {
    if (!newSurcharge.start_time || !newSurcharge.end_time || !newSurcharge.surcharge_amount) return;
    setSaving(true);
    try {
      await api.createDeliveryTimeSurcharge({
        branch_id: branchId,
        day_of_week: parseInt(newSurcharge.day_of_week),
        start_time: newSurcharge.start_time + ':00',
        end_time: newSurcharge.end_time + ':00',
        surcharge_amount: parseFloat(newSurcharge.surcharge_amount),
        surcharge_type: newSurcharge.surcharge_type,
        is_active: true,
      });
      setNewSurcharge({
        day_of_week: '0',
        start_time: '',
        end_time: '',
        surcharge_amount: '',
        surcharge_type: 'fixed',
      });
      await loadAll();
    } catch (error) {
      console.error('Error creating surcharge:', error);
      alert('שגיאה ביצירת תוספת');
    } finally {
      setSaving(false);
    }
  };

  const handleDeleteSurcharge = async (id) => {
    if (!confirm('למחוק תוספת זו?')) return;
    setSaving(true);
    try {
      await api.deleteDeliveryTimeSurcharge(id);
      await loadAll();
    } catch (error) {
      console.error('Error deleting surcharge:', error);
      alert('שגיאה במחיקת תוספת');
    } finally {
      setSaving(false);
    }
  };

  // === DISCOUNTS ===
  const handleAddDiscount = async () => {
    if (!newDiscount.min_loyalty_points || !newDiscount.discount_amount) return;
    setSaving(true);
    try {
      await api.createDeliveryLoyaltyDiscount({
        min_loyalty_points: parseInt(newDiscount.min_loyalty_points),
        discount_amount: parseFloat(newDiscount.discount_amount),
        discount_type: newDiscount.discount_type,
        is_active: true,
      });
      setNewDiscount({
        min_loyalty_points: '',
        discount_amount: '',
        discount_type: 'fixed',
      });
      await loadAll();
    } catch (error) {
      console.error('Error creating discount:', error);
      alert('שגיאה ביצירת הנחה');
    } finally {
      setSaving(false);
    }
  };

  const handleDeleteDiscount = async (id) => {
    if (!confirm('למחוק הנחה זו?')) return;
    setSaving(true);
    try {
      await api.deleteDeliveryLoyaltyDiscount(id);
      await loadAll();
    } catch (error) {
      console.error('Error deleting discount:', error);
      alert('שגיאה במחיקת הנחה');
    } finally {
      setSaving(false);
    }
  };

  if (!branchId) {
    return (
      <div className="border-t border-gray-200 pt-4 mt-2">
        <p className="text-sm text-gray-500">שמור סניף תחילה כדי לנהל תמחור משלוחים מתקדם</p>
      </div>
    );
  }

  return (
    <div className="border-t border-gray-200 pt-4 mt-2">
      <Button
        variant="ghost"
        onClick={() => setExpanded(!expanded)}
        fullWidth
        className="justify-between"
      >
        <span className="text-md font-semibold text-gray-900">תמחור משלוחים מתקדם (אופציונלי)</span>
        {expanded ? <ChevronUpIcon className="w-5 h-5" /> : <ChevronDownIcon className="w-5 h-5" />}
      </Button>

      {expanded && (
        <div className="space-y-4 mt-4">
          {/* Tabs */}
          <div className="flex gap-2 border-b border-gray-200">
            <Button
              variant={activeTab === 'tiers' ? 'primary' : 'ghost'}
              onClick={() => setActiveTab('tiers')}
              size="sm"
              className="border-b-2"
              style={{
                borderBottomColor: activeTab === 'tiers' ? 'rgb(239, 68, 68)' : 'transparent',
                borderRadius: '0',
              }}
            >
              דרגות לפי ערך
            </Button>
            <Button
              variant={activeTab === 'surcharges' ? 'primary' : 'ghost'}
              onClick={() => setActiveTab('surcharges')}
              size="sm"
              className="border-b-2"
              style={{
                borderBottomColor: activeTab === 'surcharges' ? 'rgb(239, 68, 68)' : 'transparent',
                borderRadius: '0',
              }}
            >
              תוספות שעות שיא
            </Button>
            <Button
              variant={activeTab === 'discounts' ? 'primary' : 'ghost'}
              onClick={() => setActiveTab('discounts')}
              size="sm"
              className="border-b-2"
              style={{
                borderBottomColor: activeTab === 'discounts' ? 'rgb(239, 68, 68)' : 'transparent',
                borderRadius: '0',
              }}
            >
              הנחות נאמנות
            </Button>
          </div>

          {loading ? (
            <div className="text-center py-4 text-sm text-gray-500">טוען...</div>
          ) : (
            <>
              {/* TIERS TAB */}
              {activeTab === 'tiers' && (
                <div className="space-y-3">
                  <p className="text-xs text-gray-600">
                    הגדר מחירי משלוח לפי ערך ההזמנה. למשל: משלוח חינם מעל ₪100
                  </p>

                  {/* Existing Tiers */}
                  {tiers.length > 0 && (
                    <div className="border border-gray-200 rounded-lg overflow-hidden">
                      <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                          <tr>
                            <th className="px-3 py-2 text-right text-xs font-medium text-gray-700">מעל (₪)</th>
                            <th className="px-3 py-2 text-right text-xs font-medium text-gray-700">דמי משלוח (₪)</th>
                            <th className="px-3 py-2 text-right text-xs font-medium text-gray-700"></th>
                          </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                          {tiers.map((tier) => (
                            <tr key={tier.id}>
                              <td className="px-3 py-2 text-sm">{tier.min_order_value}</td>
                              <td className="px-3 py-2 text-sm">{tier.delivery_fee}</td>
                              <td className="px-3 py-2 text-sm">
                                <IconButton
                                  onClick={() => handleDeleteTier(tier.id)}
                                  disabled={saving}
                                  variant="danger"
                                  size="sm"
                                  icon={TrashIcon}
                                  aria-label="מחק דרגה"
                                />
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}

                  {/* Add New Tier */}
                  <div className="bg-gray-50 p-3 rounded-lg space-y-2">
                    <div className="grid grid-cols-2 gap-2">
                      <FormField
                        label="מעל (₪)"
                        fieldType="input"
                        type="number"
                        step="1"
                        min="0"
                        value={newTier.min_order_value}
                        onChange={(e) => setNewTier({ ...newTier, min_order_value: e.target.value })}
                        placeholder="50"
                        size="sm"
                      />
                      <FormField
                        label="דמי משלוח (₪)"
                        fieldType="input"
                        type="number"
                        step="0.5"
                        min="0"
                        value={newTier.delivery_fee}
                        onChange={(e) => setNewTier({ ...newTier, delivery_fee: e.target.value })}
                        placeholder="15"
                        size="sm"
                      />
                    </div>
                    <Button
                      variant="secondary"
                      size="sm"
                      onClick={handleAddTier}
                      disabled={saving || !newTier.min_order_value || !newTier.delivery_fee}
                      icon={PlusIcon}
                    >
                      הוסף דרגה
                    </Button>
                  </div>
                </div>
              )}

              {/* SURCHARGES TAB */}
              {activeTab === 'surcharges' && (
                <div className="space-y-3">
                  <p className="text-xs text-gray-600">
                    הגדר תוספות מחיר לשעות שיא. למשל: +₪10 בימי שישי 18:00-23:00
                  </p>

                  {/* Existing Surcharges */}
                  {surcharges.length > 0 && (
                    <div className="space-y-2">
                      {surcharges.map((surcharge) => (
                        <div key={surcharge.id} className="flex items-center justify-between p-2 bg-gray-50 rounded border border-gray-200">
                          <div className="text-sm">
                            <span className="font-medium">
                              {daysOfWeek.find((d) => d.value === String(surcharge.day_of_week))?.label}
                            </span>
                            {' '}
                            {surcharge.start_time.slice(0, 5)} - {surcharge.end_time.slice(0, 5)}
                            {' | '}
                            {surcharge.surcharge_type === 'fixed' ? '₪' : ''}
                            {surcharge.surcharge_amount}
                            {surcharge.surcharge_type === 'percentage' ? '%' : ''}
                            {!surcharge.is_active && ' (לא פעיל)'}
                          </div>
                          <IconButton
                            onClick={() => handleDeleteSurcharge(surcharge.id)}
                            disabled={saving}
                            variant="danger"
                            size="sm"
                            icon={TrashIcon}
                            aria-label="מחק תוספת"
                          />
                        </div>
                      ))}
                    </div>
                  )}

                  {/* Add New Surcharge */}
                  <div className="bg-gray-50 p-3 rounded-lg space-y-2">
                    <div className="grid grid-cols-2 gap-2">
                      <FormField
                        label="יום"
                        fieldType="select"
                        value={newSurcharge.day_of_week}
                        onChange={(e) => setNewSurcharge({ ...newSurcharge, day_of_week: e.target.value })}
                        size="sm"
                      >
                        {daysOfWeek.map((day) => (
                          <option key={day.value} value={day.value}>
                            {day.label}
                          </option>
                        ))}
                      </FormField>
                      <div className="grid grid-cols-2 gap-1">
                        <FormField
                          label="התחלה"
                          fieldType="input"
                          type="time"
                          value={newSurcharge.start_time}
                          onChange={(e) => setNewSurcharge({ ...newSurcharge, start_time: e.target.value })}
                          size="sm"
                        />
                        <FormField
                          label="סיום"
                          fieldType="input"
                          type="time"
                          value={newSurcharge.end_time}
                          onChange={(e) => setNewSurcharge({ ...newSurcharge, end_time: e.target.value })}
                          size="sm"
                        />
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                      <FormField
                        label="סכום"
                        fieldType="input"
                        type="number"
                        step="0.5"
                        min="0"
                        value={newSurcharge.surcharge_amount}
                        onChange={(e) => setNewSurcharge({ ...newSurcharge, surcharge_amount: e.target.value })}
                        placeholder="10"
                        size="sm"
                      />
                      <FormField
                        label="סוג"
                        fieldType="select"
                        value={newSurcharge.surcharge_type}
                        onChange={(e) => setNewSurcharge({ ...newSurcharge, surcharge_type: e.target.value })}
                        size="sm"
                      >
                        {surchargeTypeOptions.map((option) => (
                          <option key={option.value} value={option.value}>
                            {option.label}
                          </option>
                        ))}
                      </FormField>
                    </div>
                    <Button
                      variant="secondary"
                      size="sm"
                      onClick={handleAddSurcharge}
                      disabled={saving || !newSurcharge.start_time || !newSurcharge.end_time || !newSurcharge.surcharge_amount}
                      icon={PlusIcon}
                    >
                      הוסף תוספת
                    </Button>
                  </div>
                </div>
              )}

              {/* DISCOUNTS TAB */}
              {activeTab === 'discounts' && (
                <div className="space-y-3">
                  <p className="text-xs text-gray-600">
                    הגדר הנחות משלוח ללקוחות עם נקודות נאמנות. למשל: ₪5 הנחה עם 100+ נקודות
                  </p>

                  {/* Existing Discounts */}
                  {discounts.length > 0 && (
                    <div className="space-y-2">
                      {discounts.map((discount) => (
                        <div key={discount.id} className="flex items-center justify-between p-2 bg-gray-50 rounded border border-gray-200">
                          <div className="text-sm">
                            <span className="font-medium">{discount.min_loyalty_points}+ נקודות</span>
                            {' → '}
                            {discount.discount_type === 'fixed' ? '₪' : ''}
                            {discount.discount_amount}
                            {discount.discount_type === 'percentage' ? '%' : ''} הנחה
                            {!discount.is_active && ' (לא פעיל)'}
                          </div>
                          <IconButton
                            onClick={() => handleDeleteDiscount(discount.id)}
                            disabled={saving}
                            variant="danger"
                            size="sm"
                            icon={TrashIcon}
                            aria-label="מחק הנחה"
                          />
                        </div>
                      ))}
                    </div>
                  )}

                  {/* Add New Discount */}
                  <div className="bg-gray-50 p-3 rounded-lg space-y-2">
                    <div className="grid grid-cols-2 gap-2">
                      <FormField
                        label="נקודות מינימום"
                        fieldType="input"
                        type="number"
                        step="1"
                        min="0"
                        value={newDiscount.min_loyalty_points}
                        onChange={(e) => setNewDiscount({ ...newDiscount, min_loyalty_points: e.target.value })}
                        placeholder="100"
                        size="sm"
                      />
                      <FormField
                        label="סכום הנחה"
                        fieldType="input"
                        type="number"
                        step="0.5"
                        min="0"
                        value={newDiscount.discount_amount}
                        onChange={(e) => setNewDiscount({ ...newDiscount, discount_amount: e.target.value })}
                        placeholder="5"
                        size="sm"
                      />
                    </div>
                    <FormField
                      label="סוג הנחה"
                      fieldType="select"
                      value={newDiscount.discount_type}
                      onChange={(e) => setNewDiscount({ ...newDiscount, discount_type: e.target.value })}
                      size="sm"
                      fullWidth
                    >
                      {discountTypeOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </FormField>
                    <Button
                      variant="secondary"
                      size="sm"
                      onClick={handleAddDiscount}
                      disabled={saving || !newDiscount.min_loyalty_points || !newDiscount.discount_amount}
                      icon={PlusIcon}
                    >
                      הוסף הנחה
                    </Button>
                  </div>
                </div>
              )}
            </>
          )}
        </div>
      )}
    </div>
  );
};

export default DeliveryPricingSection;
