/**
 * Translations for Customer App
 *
 * Simple translation system that can be extended with a full i18n library later
 * Currently supports: English (default), Hebrew, Arabic
 *
 * Usage:
 *   import { t } from '../i18n/translations';
 *   const text = t('welcome');
 */

const translations = {
  en: {
    // Common
    welcome: 'Welcome to Squidly',
    loading: 'Loading...',
    error: 'Error',
    success: 'Success',
    tryAgain: 'Try Again',
    back: 'Back',
    next: 'Next',
    continue: 'Continue',
    cancel: 'Cancel',
    confirm: 'Confirm',
    save: 'Save',

    // Branch Selection
    chooseBranch: 'Choose Your Branch',
    selectBranchPrompt: 'Select a branch to start ordering',
    selectedBranch: 'Selected',
    selectBranch: 'Select Branch',
    openNow: 'Open Now',
    closed: 'Closed',
    opens: 'Opens',
    noBranchesAvailable: 'No Branches Available',
    noBranchesMessage: 'We couldn\'t find any branches at the moment. Please check back later.',
    failedToLoadBranches: 'Failed to Load Branches',
    branchTip: 'Tip: You can only order from branches that are currently open',

    // Cart
    cart: 'Cart',
    addToCart: 'Add to Cart',
    removeFromCart: 'Remove',
    emptyCart: 'Your cart is empty',
    cartCount: 'items',
    subtotal: 'Subtotal',
    total: 'Total',
    proceedToCheckout: 'Proceed to Checkout',
    continueShopping: 'Continue Shopping',

    // Checkout
    checkout: 'Checkout',
    customerInfo: 'Customer Information',
    deliveryInfo: 'Delivery Information',
    payment: 'Payment',
    placeOrder: 'Place Order',
    orderConfirmation: 'Order Confirmation',

    // Product Catalog
    menu: 'Menu',
    products: 'Products',
    categories: 'Categories',
    viewDetails: 'View Details',
    customize: 'Customize',
    noProducts: 'No products available',
    searchProducts: 'Search products...',

    // Order Tracking
    orderTracking: 'Order Tracking',
    trackOrder: 'Track Your Order',
    orderNumber: 'Order Number',
    orderStatus: 'Order Status',
    estimatedTime: 'Estimated Time',

    // API Status
    apiInitialized: 'API initialized successfully',
    apiInitializing: 'Initializing API...',
    apiFailed: 'Failed to initialize API',
    testApiEndpoints: 'Test API Endpoints',

    // Development
    developmentStatus: 'Development Status',
    accessAt: 'Access this at',
    currentView: 'Current view',
  },

  he: {
    // Common (Hebrew - RTL)
    welcome: 'ברוכים הבאים לסקווידלי',
    loading: 'טוען...',
    error: 'שגיאה',
    success: 'הצלחה',
    tryAgain: 'נסה שוב',
    back: 'חזרה',
    next: 'הבא',
    continue: 'המשך',
    cancel: 'ביטול',
    confirm: 'אישור',
    save: 'שמור',

    // Branch Selection
    chooseBranch: 'בחר את הסניף שלך',
    selectBranchPrompt: 'בחר סניף כדי להתחיל להזמין',
    selectedBranch: 'נבחר',
    selectBranch: 'בחר סניף',
    openNow: 'פתוח עכשיו',
    closed: 'סגור',
    opens: 'נפתח',
    noBranchesAvailable: 'אין סניפים זמינים',
    noBranchesMessage: 'לא מצאנו סניפים כרגע. אנא נסה שוב מאוחר יותר.',
    failedToLoadBranches: 'טעינת הסניפים נכשלה',
    branchTip: 'טיפ: ניתן להזמין רק מסניפים שפתוחים כרגע',

    // Cart
    cart: 'סל קניות',
    addToCart: 'הוסף לסל',
    removeFromCart: 'הסר',
    emptyCart: 'הסל שלך ריק',
    cartCount: 'פריטים',
    subtotal: 'ביניים',
    total: 'סה"כ',
    proceedToCheckout: 'המשך לתשלום',
    continueShopping: 'המשך בקניות',

    // Checkout
    checkout: 'תשלום',
    customerInfo: 'פרטי לקוח',
    deliveryInfo: 'פרטי משלוח',
    payment: 'תשלום',
    placeOrder: 'בצע הזמנה',
    orderConfirmation: 'אישור הזמנה',

    // Product Catalog
    menu: 'תפריט',
    products: 'מוצרים',
    categories: 'קטגוריות',
    viewDetails: 'צפה בפרטים',
    customize: 'התאם אישית',
    noProducts: 'אין מוצרים זמינים',
    searchProducts: 'חפש מוצרים...',

    // Order Tracking
    orderTracking: 'מעקב הזמנה',
    trackOrder: 'עקוב אחר ההזמנה שלך',
    orderNumber: 'מספר הזמנה',
    orderStatus: 'סטטוס הזמנה',
    estimatedTime: 'זמן משוער',

    // API Status
    apiInitialized: 'API אותחל בהצלחה',
    apiInitializing: 'מאתחל API...',
    apiFailed: 'איתחול API נכשל',
    testApiEndpoints: 'בדוק נקודות קצה של API',

    // Development
    developmentStatus: 'סטטוס פיתוח',
    accessAt: 'גש ב',
    currentView: 'תצוגה נוכחית',
  },

  ar: {
    // Common (Arabic - RTL)
    welcome: 'مرحبا بكم في سكويدلي',
    loading: 'جار التحميل...',
    error: 'خطأ',
    success: 'نجاح',
    tryAgain: 'حاول مرة أخرى',
    back: 'رجوع',
    next: 'التالي',
    continue: 'متابعة',
    cancel: 'إلغاء',
    confirm: 'تأكيد',
    save: 'حفظ',

    // Branch Selection
    chooseBranch: 'اختر الفرع الخاص بك',
    selectBranchPrompt: 'اختر فرعًا لبدء الطلب',
    selectedBranch: 'محدد',
    selectBranch: 'اختر الفرع',
    openNow: 'مفتوح الآن',
    closed: 'مغلق',
    opens: 'يفتح',
    noBranchesAvailable: 'لا توجد فروع متاحة',
    noBranchesMessage: 'لم نتمكن من العثور على أي فروع في الوقت الحالي. يرجى التحقق مرة أخرى لاحقًا.',
    failedToLoadBranches: 'فشل تحميل الفروع',
    branchTip: 'نصيحة: يمكنك الطلب فقط من الفروع المفتوحة حاليًا',

    // Cart
    cart: 'السلة',
    addToCart: 'أضف إلى السلة',
    removeFromCart: 'إزالة',
    emptyCart: 'سلتك فارغة',
    cartCount: 'العناصر',
    subtotal: 'المجموع الفرعي',
    total: 'المجموع',
    proceedToCheckout: 'المتابعة إلى الدفع',
    continueShopping: 'متابعة التسوق',

    // Checkout
    checkout: 'الدفع',
    customerInfo: 'معلومات العميل',
    deliveryInfo: 'معلومات التسليم',
    payment: 'الدفع',
    placeOrder: 'تقديم الطلب',
    orderConfirmation: 'تأكيد الطلب',

    // Product Catalog
    menu: 'القائمة',
    products: 'المنتجات',
    categories: 'الفئات',
    viewDetails: 'عرض التفاصيل',
    customize: 'تخصيص',
    noProducts: 'لا توجد منتجات متاحة',
    searchProducts: 'بحث عن المنتجات...',

    // Order Tracking
    orderTracking: 'تتبع الطلب',
    trackOrder: 'تتبع طلبك',
    orderNumber: 'رقم الطلب',
    orderStatus: 'حالة الطلب',
    estimatedTime: 'الوقت المقدر',

    // API Status
    apiInitialized: 'تم تهيئة API بنجاح',
    apiInitializing: 'جاري تهيئة API...',
    apiFailed: 'فشلت تهيئة API',
    testApiEndpoints: 'اختبار نقاط نهاية API',

    // Development
    developmentStatus: 'حالة التطوير',
    accessAt: 'الوصول في',
    currentView: 'العرض الحالي',
  },
};

// Current language (default: Hebrew)
// Can be set from WordPress settings or user preference later
let currentLanguage = 'he';

/**
 * Set the current language
 * @param {string} lang - Language code ('en', 'he', 'ar')
 */
export function setLanguage(lang) {
  if (translations[lang]) {
    currentLanguage = lang;
    // Update document direction for RTL languages
    document.documentElement.dir = (lang === 'he' || lang === 'ar') ? 'rtl' : 'ltr';
    document.documentElement.lang = lang;
  } else {
    console.warn(`Language '${lang}' not supported, using Hebrew`);
  }
}

// Initialize document direction on load
if (typeof document !== 'undefined') {
  document.documentElement.dir = 'rtl';
  document.documentElement.lang = 'he';
}

/**
 * Get translated string
 * @param {string} key - Translation key
 * @param {object} vars - Variables to interpolate (optional)
 * @returns {string} Translated string
 *
 * @example
 * t('welcome') // 'Welcome to Squidly'
 * t('selectedBranch') // 'Selected'
 */
export function t(key, vars = {}) {
  const languageStrings = translations[currentLanguage] || translations.en;
  let text = languageStrings[key] || key;

  // Simple variable interpolation: "Hello {{name}}" with { name: 'John' }
  Object.keys(vars).forEach(varKey => {
    text = text.replace(new RegExp(`{{${varKey}}}`, 'g'), vars[varKey]);
  });

  return text;
}

/**
 * Get current language
 * @returns {string} Current language code
 */
export function getCurrentLanguage() {
  return currentLanguage;
}

/**
 * Get available languages
 * @returns {array} Array of language objects
 */
export function getAvailableLanguages() {
  return [
    { code: 'en', name: 'English', nativeName: 'English', direction: 'ltr' },
    { code: 'he', name: 'Hebrew', nativeName: 'עברית', direction: 'rtl' },
    { code: 'ar', name: 'Arabic', nativeName: 'العربية', direction: 'rtl' },
  ];
}

export default { t, setLanguage, getCurrentLanguage, getAvailableLanguages };
