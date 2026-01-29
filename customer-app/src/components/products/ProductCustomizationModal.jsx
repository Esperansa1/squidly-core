import React, { useState, useEffect } from 'react';
import publicApi from '../../services/publicApi';
import theme from '../../config/theme';
import { getCurrentLanguage } from '../../i18n/translations';
import { useIsMobile } from '../../hooks/useMediaQuery';

/**
 * ProductCustomizationModal - Responsive modal for product customization
 * Mobile: Full-screen modal with back button
 * Desktop: Phone-shaped modal (420px max-width)
 * Layout: Product image at top, name + quantity selector, description, groups, add button
 * Supports RTL/LTR based on language detection
 * Supports editing existing cart items
 */
export default function ProductCustomizationModal({ product, isOpen, onClose, onConfirm, editingItem }) {
  const isMobile = useIsMobile();
  const [productData, setProductData] = useState(null);
  const [selections, setSelections] = useState({});
  const [quantity, setQuantity] = useState(1);
  const [loading, setLoading] = useState(true);
  const [validationErrors, setValidationErrors] = useState({});
  const [direction, setDirection] = useState('rtl');
  const isEditing = !!editingItem; // Track if we're in editing mode

  // Detect direction when modal opens
  useEffect(() => {
    if (isOpen) {
      const lang = getCurrentLanguage();
      const newDirection = (lang === 'he' || lang === 'ar') ? 'rtl' : 'ltr';
      setDirection(newDirection);
    }
  }, [isOpen]);

  // Fetch product with groups when modal opens
  useEffect(() => {
    if (isOpen && product) {
      fetchProductWithGroups();
      // Initialize quantity from editing item or default to 1
      setQuantity(editingItem?.quantity || 1);
    }
  }, [isOpen, product, editingItem]);

  const fetchProductWithGroups = async () => {
    setLoading(true);
    try {
      const data = await publicApi.getProductWithGroups(product.id);
      setProductData(data);

      // Initialize selections from editing item or empty
      let initialSelections = {};
      if (editingItem && editingItem.customizations) {
        // If editing, restore previous selections
        initialSelections = editingItem.customizations;
      } else {
        // If new, initialize empty selections
        data.groups_product_data?.forEach(group => {
          initialSelections[group.group_id] = [];
        });
      }

      setSelections(initialSelections);
      setValidationErrors({});
    } catch (error) {
      console.error('❌ Failed to load product groups:', error);
    } finally {
      setLoading(false);
    }
  };

  // Get constraint label for a group
  const getConstraintLabel = (group) => {
    const min = group.min_selections || 0;
    const max = group.max_selections || 0;

    if (min === 0 && max === 0) {
      return 'בחר את התוספות שתרצה לצד המנה.';
    } else if (min === 0 && max > 0) {
      return `בחר עד ${max} ${max === 1 ? 'פריט' : 'פריטים'}`;
    } else if (min > 0 && max === 0) {
      return `בחר לפחות ${min} ${min === 1 ? 'פריט' : 'פריטים'}`;
    } else if (min === max) {
      return `בחר בדיוק ${min} ${min === 1 ? 'פריט' : 'פריטים'}`;
    } else {
      return `בחר בין ${min}-${max} פריטים`;
    }
  };

  // Toggle item selection in a group (for checkboxes)
  const toggleItem = (groupId, item, group) => {
    setSelections(prev => {
      const groupSelections = prev[groupId] || [];
      const isSelected = groupSelections.some(s => s.id === item.id);

      if (isSelected) {
        return {
          ...prev,
          [groupId]: groupSelections.filter(s => s.id !== item.id)
        };
      } else {
        const maxSelections = group.max_selections || 0;
        if (maxSelections > 0 && groupSelections.length >= maxSelections) {
          return prev;
        }
        return {
          ...prev,
          [groupId]: [...groupSelections, item]
        };
      }
    });

    setValidationErrors(prev => {
      const newErrors = { ...prev };
      delete newErrors[groupId];
      return newErrors;
    });
  };

  // Select single item (for radio buttons)
  const selectSingleItem = (groupId, item) => {
    setSelections(prev => ({
      ...prev,
      [groupId]: [item]
    }));

    setValidationErrors(prev => {
      const newErrors = { ...prev };
      delete newErrors[groupId];
      return newErrors;
    });
  };

  // Validate all selections
  const validateSelections = () => {
    const errors = {};
    productData?.groups_product_data?.forEach(group => {
      const selectedCount = (selections[group.group_id] || []).length;
      const min = group.min_selections || 0;
      const max = group.max_selections || 0;

      if (selectedCount < min) {
        errors[group.group_id] = `יש לבחור לפחות ${min} ${min === 1 ? 'פריט' : 'פריטים'}`;
      }
      if (max > 0 && selectedCount > max) {
        errors[group.group_id] = `ניתן לבחור עד ${max} ${max === 1 ? 'פריט' : 'פריטים'}`;
      }
    });
    return errors;
  };

  // Calculate total price
  const calculateTotalPrice = () => {
    if (!productData) return 0;
    const basePrice = productData.discounted_price || productData.price;
    const addonsPrice = Object.values(selections)
      .flat()
      .reduce((sum, item) => sum + (item.price || 0), 0);
    return (basePrice + addonsPrice) * quantity;
  };

  // Handle confirm
  const handleConfirm = () => {
    const errors = validateSelections();
    if (Object.keys(errors).length > 0) {
      setValidationErrors(errors);
      return;
    }

    const customizationsToSend = {};
    Object.keys(selections).forEach(groupId => {
      const numericGroupId = parseInt(groupId);
      if (!isNaN(numericGroupId) && numericGroupId > 0 && selections[groupId] && selections[groupId].length > 0) {
        customizationsToSend[groupId] = selections[groupId];
      }
    });

    const customizedProduct = {
      ...product,
      customizations: customizationsToSend,
      quantity,
      final_price: calculateTotalPrice()
    };
    onConfirm(customizedProduct);
    onClose();
  };

  if (!isOpen) return null;

  const isMinusDisabled = quantity <= 1;

  return (
    // Dark overlay background
    <div
      style={{
        position: 'fixed',
        inset: 0,
        backgroundColor: 'rgba(0, 0, 0, 0.5)',
        zIndex: 9999,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: theme.spacing.md,
      }}
      onClick={onClose}
    >
      {/* Modal container - full-screen on mobile, phone-shaped on desktop */}
      <div
        style={{
          backgroundColor: theme.colors.cardBg,
          borderRadius: isMobile ? 0 : theme.borderRadius.xl,
          maxWidth: isMobile ? '100%' : '420px',
          width: '100%',
          height: isMobile ? '100%' : 'auto',
          maxHeight: isMobile ? '100%' : (window.innerHeight < 700 ? '95vh' : '90vh'),
          display: 'flex',
          flexDirection: 'column',
          overflow: 'hidden',
          boxShadow: isMobile ? 'none' : '0 20px 50px rgba(0, 0, 0, 0.3)',
          direction: direction,
          ...(isMobile && {
            position: 'fixed',
            inset: 0,
          }),
        }}
        onClick={(e) => e.stopPropagation()}
      >
        {loading ? (
          // Skeleton loader
          <div style={{ padding: theme.spacing.lg }}>
            {/* Image skeleton */}
            <div style={{
              width: '100%',
              height: '200px',
              backgroundColor: theme.colors.background,
              borderRadius: theme.borderRadius.lg,
              marginBottom: theme.spacing.lg,
              animation: 'pulse 1.5s ease-in-out infinite',
            }} />

            {/* Title + Quantity row skeleton */}
            <div style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              marginBottom: theme.spacing.md,
            }}>
              <div style={{
                width: '60%',
                height: '24px',
                backgroundColor: theme.colors.background,
                borderRadius: theme.borderRadius.md,
                animation: 'pulse 1.5s ease-in-out infinite',
              }} />
              <div style={{
                width: '100px',
                height: '32px',
                backgroundColor: theme.colors.background,
                borderRadius: theme.borderRadius.full,
                animation: 'pulse 1.5s ease-in-out infinite',
              }} />
            </div>

            {/* Description skeleton */}
            <div style={{
              width: '90%',
              height: '16px',
              backgroundColor: theme.colors.background,
              borderRadius: theme.borderRadius.md,
              marginBottom: theme.spacing.md,
              animation: 'pulse 1.5s ease-in-out infinite',
            }} />

            {/* Group section skeleton */}
            <div style={{
              backgroundColor: theme.colors.background,
              borderRadius: theme.borderRadius.lg,
              padding: theme.spacing.md,
              marginBottom: theme.spacing.md,
            }}>
              <div style={{
                width: '40%',
                height: '18px',
                backgroundColor: theme.colors.cardBg,
                borderRadius: theme.borderRadius.md,
                marginBottom: theme.spacing.sm,
                animation: 'pulse 1.5s ease-in-out infinite',
              }} />
              <div style={{
                width: '70%',
                height: '14px',
                backgroundColor: theme.colors.cardBg,
                borderRadius: theme.borderRadius.md,
                animation: 'pulse 1.5s ease-in-out infinite',
              }} />
            </div>
          </div>
        ) : (
          <>
            {/* Product Image at top */}
            {productData?.image_url && (
              <div
                style={{
                  width: '100%',
                  height: '200px',
                  maxHeight: '30vh',
                  overflow: 'hidden',
                  flexShrink: 0,
                }}
              >
                <img
                  src={productData.image_url}
                  alt={productData.name}
                  style={{
                    width: '100%',
                    height: '100%',
                    objectFit: 'cover',
                  }}
                />
              </div>
            )}

            {/* Scrollable content area */}
            <div
              style={{
                flex: 1,
                overflowY: 'auto',
                padding: `${theme.spacing.lg} ${theme.spacing.lg} 0 ${theme.spacing.lg}`,
              }}
            >
              {/* Product name + quantity selector */}
              <div
                style={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  marginBottom: theme.spacing.md,
                }}
              >
                <h2
                  style={{
                    fontSize: '1.5rem',
                    fontWeight: '700',
                    color: theme.colors.text.primary,
                    margin: 0,
                    flex: 1,
                  }}
                >
                  {productData?.name}
                </h2>

                {/* Quantity selector */}
                <div
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: theme.spacing.sm,
                  }}
                >
                  <button
                    onClick={() => setQuantity(Math.max(1, quantity - 1))}
                    disabled={isMinusDisabled}
                    style={{
                      width: '32px',
                      height: '32px',
                      borderRadius: theme.borderRadius.full,
                      backgroundColor: isMinusDisabled ? theme.colors.background : theme.colors.primary,
                      color: isMinusDisabled ? theme.colors.text.muted : theme.colors.text.white,
                      border: 'none',
                      cursor: isMinusDisabled ? 'not-allowed' : 'pointer',
                      opacity: isMinusDisabled ? 0.5 : 1,
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      fontSize: '1.25rem',
                      fontWeight: 'bold',
                      lineHeight: 1,
                      padding: 0,
                      transition: 'all 0.2s ease',
                    }}
                    onMouseEnter={(e) => {
                      if (!isMinusDisabled) {
                        e.currentTarget.style.backgroundColor = theme.colors.primaryHover;
                      }
                    }}
                    onMouseLeave={(e) => {
                      if (!isMinusDisabled) {
                        e.currentTarget.style.backgroundColor = theme.colors.primary;
                      }
                    }}
                  >
                    −
                  </button>
                  <span
                    style={{
                      fontSize: '1.125rem',
                      fontWeight: '500',
                      minWidth: '30px',
                      textAlign: 'center',
                    }}
                  >
                    {quantity}
                  </span>
                  <button
                    onClick={() => setQuantity(quantity + 1)}
                    style={{
                      width: '32px',
                      height: '32px',
                      borderRadius: theme.borderRadius.full,
                      backgroundColor: theme.colors.primary,
                      color: theme.colors.text.white,
                      border: 'none',
                      cursor: 'pointer',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      fontSize: '1.25rem',
                      fontWeight: 'bold',
                      lineHeight: 1,
                      padding: 0,
                      transition: 'all 0.2s ease',
                    }}
                    onMouseEnter={(e) => {
                      e.currentTarget.style.backgroundColor = theme.colors.primaryHover;
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.backgroundColor = theme.colors.primary;
                    }}
                  >
                    +
                  </button>
                </div>
              </div>

              {/* Product description */}
              <p
                style={{
                  fontSize: '0.9375rem',
                  color: theme.colors.text.primary,
                  opacity: 0.8,
                  lineHeight: '1.5',
                  marginBottom: theme.spacing.md,
                }}
              >
                {productData?.description || 'תיאור המוצר יופיע כאן'}
              </p>

              {/* Product Groups */}
              {productData?.groups_product_data?.map((group) => {
                const isSingleChoice = group.max_selections === 1;
                const hasError = validationErrors[group.group_id];

                return (
                  <div
                    key={group.group_id}
                    style={{
                      backgroundColor: '#FAFAFA',
                      borderRadius: theme.borderRadius.lg,
                      padding: theme.spacing.md,
                      marginBottom: theme.spacing.md,
                      boxShadow: theme.shadows.sm,
                    }}
                  >
                    {/* Group name */}
                    <h3
                      style={{
                        fontSize: '1.125rem',
                        fontWeight: '600',
                        color: theme.colors.text.primary,
                        margin: 0,
                        marginBottom: theme.spacing.xs,
                      }}
                    >
                      {group.group_name}:
                    </h3>

                    {/* Group description + constraint */}
                    <p
                      style={{
                        fontSize: '0.875rem',
                        color: theme.colors.text.secondary,
                        lineHeight: '1.4',
                        margin: 0,
                        marginBottom: theme.spacing.sm,
                      }}
                    >
                      {getConstraintLabel(group)}
                    </p>

                    {/* Validation error */}
                    {hasError && (
                      <div
                        style={{
                          padding: theme.spacing.sm,
                          backgroundColor: 'rgba(239, 68, 68, 0.1)',
                          border: `1px solid rgba(239, 68, 68, 0.3)`,
                          borderRadius: theme.borderRadius.md,
                          color: theme.colors.error,
                          fontSize: '0.875rem',
                          marginBottom: theme.spacing.md,
                          boxShadow: '0 2px 4px rgba(220, 38, 38, 0.1)',
                        }}
                      >
                        {hasError}
                      </div>
                    )}

                    {/* Group items */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.sm }}>
                      {group.items.map((item) => {
                        const isSelected = selections[group.group_id]?.some((s) => s.id === item.id);

                        return (
                          <label
                            key={item.id}
                            style={{
                              display: 'flex',
                              alignItems: 'center',
                              justifyContent: 'space-between',
                              cursor: 'pointer',
                              gap: theme.spacing.sm,
                            }}
                            onMouseEnter={(e) => {
                              const checkbox = e.currentTarget.querySelector('div');
                              if (!isSelected && checkbox) {
                                checkbox.style.borderColor = theme.colors.text.secondary;
                              }
                            }}
                            onMouseLeave={(e) => {
                              const checkbox = e.currentTarget.querySelector('div');
                              if (!isSelected && checkbox) {
                                checkbox.style.borderColor = theme.colors.border;
                              }
                            }}
                          >
                            {/* Item name - Far right in RTL */}
                            <span
                              style={{
                                fontSize: '0.9375rem',
                                color: theme.colors.text.primary,
                                textAlign: direction === 'rtl' ? 'right' : 'left',
                              }}
                            >
                              {item.name}
                            </span>

                            {/* Hidden input for functionality */}
                            <input
                              type={isSingleChoice ? 'radio' : 'checkbox'}
                              name={isSingleChoice ? `group_${group.group_id}` : undefined}
                              checked={isSelected}
                              onChange={() => {
                                if (isSingleChoice) {
                                  selectSingleItem(group.group_id, item);
                                } else {
                                  toggleItem(group.group_id, item, group);
                                }
                              }}
                              style={{ display: 'none' }}
                            />

                            {/* Button and Price container - Far left in RTL */}
                            <div
                              style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: theme.spacing.sm,
                              }}
                            >
                              {/* Price - Next to button if exists */}
                              {item.price > 0 && (
                                <span
                                  style={{
                                    fontSize: '0.875rem',
                                    color: theme.colors.text.secondary,
                                  }}
                                >
                                  {item.price.toFixed(2)} ₪
                                </span>
                              )}

                              {/* Radio or Checkbox */}
                              <div
                                style={{
                                  width: '20px',
                                  height: '20px',
                                  borderRadius: isSingleChoice ? '50%' : '4px',
                                  border: `2px solid ${isSelected ? theme.colors.primary : theme.colors.border}`,
                                  backgroundColor: isSelected ? theme.colors.primary : theme.colors.cardBg,
                                  display: 'flex',
                                  alignItems: 'center',
                                  justifyContent: 'center',
                                  flexShrink: 0,
                                  transition: 'all 0.2s ease',
                                  transform: isSelected ? 'scale(1.05)' : 'scale(1)',
                                }}
                              >
                                {isSelected && (
                                  isSingleChoice ? (
                                    <div
                                      style={{
                                        width: '10px',
                                        height: '10px',
                                        borderRadius: '50%',
                                        backgroundColor: theme.colors.cardBg,
                                      }}
                                    />
                                  ) : (
                                    <svg
                                      width="14"
                                      height="14"
                                      viewBox="0 0 14 14"
                                      fill="none"
                                      xmlns="http://www.w3.org/2000/svg"
                                    >
                                      <path
                                        d="M11.6666 3.5L5.24992 9.91667L2.33325 7"
                                        stroke="white"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                      />
                                    </svg>
                                  )
                                )}
                              </div>
                            </div>
                          </label>
                        );
                      })}
                    </div>
                  </div>
                );
              })}
            </div>

            {/* Add to cart button at bottom */}
            <div
              style={{
                padding: theme.spacing.lg,
                borderTop: `1px solid ${theme.colors.border}`,
                flexShrink: 0,
              }}
            >
              <button
                onClick={handleConfirm}
                style={{
                  width: '100%',
                  padding: theme.spacing.md,
                  backgroundColor: theme.colors.primary,
                  color: theme.colors.text.white,
                  border: 'none',
                  borderRadius: theme.borderRadius.lg,
                  fontSize: '1.125rem',
                  fontWeight: 'bold',
                  cursor: 'pointer',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  boxShadow: theme.shadows.md,
                  transition: 'all 0.2s ease',
                }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.backgroundColor = theme.colors.primaryHover;
                  e.currentTarget.style.boxShadow = theme.shadows.lg;
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.backgroundColor = theme.colors.primary;
                  e.currentTarget.style.boxShadow = theme.shadows.md;
                }}
              >
                <span>{isEditing ? 'עדכן' : 'הוסף עכשיו'}</span>
                <span>{calculateTotalPrice().toFixed(2)} ₪</span>
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
