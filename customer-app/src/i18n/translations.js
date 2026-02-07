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
    backToCart: 'Back to Cart',
    customerInfo: 'Customer Information',
    deliveryInfo: 'Delivery Information',
    delivery: 'Delivery',
    review: 'Review',
    payment: 'Payment',
    placeOrder: 'Place Order',
    orderConfirmation: 'Order Confirmation',

    // Customer Info Step
    enterYourDetails: 'Please enter your contact information',
    firstName: 'First Name',
    lastName: 'Last Name',
    enterFirstName: 'Enter first name',
    enterLastName: 'Enter last name',
    phone: 'Phone',
    optional: 'optional',
    email: 'Email',
    invalidPhone: 'Invalid phone number',
    invalidEmail: 'Invalid email address',
    phoneUsedForOrderUpdates: 'We\'ll use this to send you order updates',
    emailForReceipt: 'Optional: Receive order receipt via email',
    privacyNotice: 'Privacy Notice',
    guestCheckoutInfo: 'Your information will be used only for this order. We respect your privacy.',

    // Delivery Step
    deliveryOptions: 'Delivery Options',
    selectDeliveryMethod: 'How would you like to receive your order?',
    pickup: 'Pickup',
    pickupAtBranch: 'Pick up at branch',
    deliverToAddress: 'Deliver to address',
    deliveryAddress: 'Delivery Address',
    enterFullAddress: 'Enter your full delivery address',
    includeStreetCityApt: 'Include street, city, and apartment number',
    calculatingDeliveryFee: 'Calculating delivery fee',
    addressNotDeliverable: 'Address is outside delivery area',
    failedToCalculateFee: 'Failed to calculate delivery fee',
    freeDelivery: 'Free Delivery',
    orderAboveFreeThreshold: 'Your order qualifies for free delivery (minimum ₪{{threshold}})',
    deliveryFee: 'Delivery Fee',
    freeDeliveryAt: 'Free delivery for orders above ₪{{threshold}}',
    deliveryTime: 'Delivery Time',
    pickupTime: 'Pickup Time',
    selectPreferredDeliveryTime: 'Select your preferred delivery time',
    selectPreferredPickupTime: 'Select your preferred pickup time',
    summary: 'Summary',
    method: 'Method',
    address: 'Address',
    time: 'Time',
    free: 'Free',

    // Review Step
    reviewOrder: 'Review Your Order',
    reviewBeforePayment: 'Please review your order details before proceeding to payment',
    name: 'Name',
    edit: 'Edit',
    orderItems: 'Order Items',
    notes: 'Notes',
    priceBreakdown: 'Price Breakdown',
    tax: 'Tax',
    beforeContinuing: 'Before Continuing',
    termsNotice: 'By placing this order, you agree to our terms and conditions.',

    // Payment Step
    creatingCustomer: 'Creating customer profile',
    creatingOrder: 'Creating your order',
    redirectingToPayment: 'Redirecting to payment',
    checkoutFailed: 'Checkout failed',
    orderConfirmed: 'Order Confirmed',
    trackingToken: 'Tracking Token',
    trackYourOrder: 'Track Your Order',
    useTokenToTrack: 'Use your tracking token to check order status at any time',
    confirmationSentTo: 'Confirmation sent to {{phone}}',
    doNotCloseWindow: 'Please do not close this window',
    saveTrackingToken: 'Save this token to track your order later',
    readyToComplete: 'Ready to complete your order',
    completeOrder: 'Complete Order',

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
    enterTrackingDetails: 'Enter your order number and tracking token to view order status',
    orderNumberHelp: 'Found in your order confirmation',
    trackingTokenHelp: 'Sent to you via SMS or email after placing order',
    trackMyOrder: 'Track My Order',
    whereToFind: 'Where can I find this information?',
    confirmationEmail: 'In your order confirmation email',
    confirmationSMS: 'In your order confirmation SMS',
    checkoutConfirmation: 'On the checkout confirmation page',
    example: 'Example',
    enterOrderIdAndToken: 'Please enter both order number and tracking token',
    enterDifferentOrder: 'Track Different Order',
    order: 'Order',
    refresh: 'Refresh',
    loadingOrder: 'Loading order details...',
    autoUpdating: 'Auto-updating every 10 seconds',
    orderProgress: 'Order Progress',
    currentStatus: 'Current Status',
    orderDetails: 'Order Details',
    orderDate: 'Order Date',
    deliveryType: 'Delivery Type',
    paymentStatus: 'Payment Status',
    paymentStatus_pending: 'Pending',
    paymentStatus_paid: 'Paid',
    paymentStatus_failed: 'Failed',
    statusPending: 'Order Placed',
    statusConfirmed: 'Confirmed',
    statusPreparing: 'Preparing',
    statusReady: 'Ready for Pickup/Delivery',
    statusCompleted: 'Completed',
    statusCancelled: 'Cancelled',
    orderCancelled: 'Order Cancelled',
    orderCancelledMessage: 'This order has been cancelled and will not be fulfilled.',
    cancelledAt: 'Cancelled at',

    // API Status
    apiInitialized: 'API initialized successfully',
    apiInitializing: 'Initializing API...',
    apiFailed: 'Failed to initialize API',
    testApiEndpoints: 'Test API Endpoints',

    // Development
    developmentStatus: 'Development Status',
    accessAt: 'Access this at',
    currentView: 'Current view',

    // Branch Selection Modal
    branchModalTitle: 'Eaten with us before?',
    branchModalLogin: 'Login',
    branchModalWelcomeBack: 'Welcome back',
    branchModalPickup: 'Pickup',
    branchModalDelivery: 'Delivery',
    branchModalCity: 'City',
    branchModalStreet: 'Street',
    branchModalHouseNumber: 'House Number',
    branchModalFindingBranch: 'Finding nearest branch...',
    branchModalNotInRange: 'Address is not in delivery range',
    branchModalSelectBranch: 'Select Branch',
    branchModalSearchBranches: 'Search branches...',
    branchModalWhenArrive: 'When would you like to arrive?',
    branchModalBack: 'Back',
    branchModalContinue: 'Continue',
    branchModalNoBranchFound: 'No branch found for this area',
    branchModalRequiredField: 'This field is required',
    branchModalSelectTime: 'Select Time',

    // Loyalty Points
    loyaltyPoints: 'Loyalty Points',
    yourPoints: 'Your Points',
    pointsBalance: '{{points}} points',
    pointsYouWillEarn: 'Points you\'ll earn',
    redeemPoints: 'Redeem Points',
    usePoints: 'Use Points',
    pointsDiscount: 'Points Discount',
    pointsRedeemed: 'Points Redeemed',
    maxPointsAvailable: 'Max available: {{points}}',
    pointsEarned: 'Points Earned',
    loginToEarnPoints: 'Login to earn loyalty points',
    pointsWillBeAwarded: 'Points will be awarded when your order is completed',
    useAllPoints: 'Use All',
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
    backToCart: 'חזרה לסל',
    customerInfo: 'פרטי לקוח',
    deliveryInfo: 'פרטי משלוח',
    delivery: 'משלוח',
    review: 'סקירה',
    payment: 'תשלום',
    placeOrder: 'בצע הזמנה',
    orderConfirmation: 'אישור הזמנה',

    // Customer Info Step
    enterYourDetails: 'אנא הזן את פרטי הקשר שלך',
    firstName: 'שם פרטי',
    lastName: 'שם משפחה',
    enterFirstName: 'הזן שם פרטי',
    enterLastName: 'הזן שם משפחה',
    phone: 'טלפון',
    optional: 'אופציונלי',
    email: 'אימייל',
    invalidPhone: 'מספר טלפון לא תקין',
    invalidEmail: 'כתובת אימייל לא תקינה',
    phoneUsedForOrderUpdates: 'נשתמש בזה כדי לשלוח לך עדכוני הזמנה',
    emailForReceipt: 'אופציונלי: קבל קבלה בדוא"ל',
    privacyNotice: 'הודעת פרטיות',
    guestCheckoutInfo: 'המידע שלך ישמש רק להזמנה זו. אנו מכבדים את הפרטיות שלך.',

    // Delivery Step
    deliveryOptions: 'אפשרויות משלוח',
    selectDeliveryMethod: 'איך תרצה לקבל את ההזמנה?',
    pickup: 'איסוף עצמי',
    pickupAtBranch: 'איסוף מהסניף',
    deliverToAddress: 'משלוח לכתובת',
    deliveryAddress: 'כתובת למשלוח',
    enterFullAddress: 'הזן את הכתובת המלאה למשלוח',
    includeStreetCityApt: 'כולל רחוב, עיר ומספר דירה',
    calculatingDeliveryFee: 'מחשב דמי משלוח',
    addressNotDeliverable: 'הכתובת מחוץ לאזור המשלוח',
    failedToCalculateFee: 'נכשל בחישוב דמי משלוח',
    freeDelivery: 'משלוח חינם',
    orderAboveFreeThreshold: 'ההזמנה שלך זכאית למשלוח חינם (מינימום ₪{{threshold}})',
    deliveryFee: 'דמי משלוח',
    freeDeliveryAt: 'משלוח חינם להזמנות מעל ₪{{threshold}}',
    deliveryTime: 'זמן משלוח',
    pickupTime: 'זמן איסוף',
    selectPreferredDeliveryTime: 'בחר זמן משלוח מועדף',
    selectPreferredPickupTime: 'בחר זמן איסוף מועדף',
    summary: 'סיכום',
    method: 'שיטה',
    address: 'כתובת',
    time: 'זמן',
    free: 'חינם',

    // Review Step
    reviewOrder: 'סקור את ההזמנה',
    reviewBeforePayment: 'אנא סקור את פרטי ההזמנה לפני המעבר לתשלום',
    name: 'שם',
    edit: 'ערוך',
    orderItems: 'פריטי הזמנה',
    notes: 'הערות',
    priceBreakdown: 'פירוט מחיר',
    tax: 'מע"מ',
    beforeContinuing: 'לפני המשך',
    termsNotice: 'בביצוע הזמנה זו, אתה מסכים לתנאים וההגבלות שלנו.',

    // Payment Step
    creatingCustomer: 'יוצר פרופיל לקוח',
    creatingOrder: 'יוצר את ההזמנה שלך',
    redirectingToPayment: 'מפנה לתשלום',
    checkoutFailed: 'התשלום נכשל',
    orderConfirmed: 'ההזמנה אושרה',
    trackingToken: 'טוקן מעקב',
    trackYourOrder: 'עקוב אחר ההזמנה שלך',
    useTokenToTrack: 'השתמש בטוקן המעקב כדי לבדוק את סטטוס ההזמנה בכל עת',
    confirmationSentTo: 'אישור נשלח ל-{{phone}}',
    doNotCloseWindow: 'אנא אל תסגור את החלון',
    saveTrackingToken: 'שמור את הטוקן כדי לעקוב אחר ההזמנה מאוחר יותר',
    readyToComplete: 'מוכן להשלים את ההזמנה',
    completeOrder: 'השלם הזמנה',

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
    enterTrackingDetails: 'הזן את מספר ההזמנה ואת טוקן המעקב כדי לצפות בסטטוס ההזמנה',
    orderNumberHelp: 'נמצא באישור ההזמנה שלך',
    trackingTokenHelp: 'נשלח אליך ב-SMS או במייל לאחר ביצוע ההזמנה',
    trackMyOrder: 'עקוב אחר ההזמנה שלי',
    whereToFind: 'איפה אני יכול למצוא את המידע הזה?',
    confirmationEmail: 'במייל אישור ההזמנה',
    confirmationSMS: 'ב-SMS אישור ההזמנה',
    checkoutConfirmation: 'בדף אישור התשלום',
    example: 'דוגמה',
    enterOrderIdAndToken: 'אנא הזן גם מספר הזמנה וגם טוקן מעקב',
    enterDifferentOrder: 'עקוב אחר הזמנה אחרת',
    order: 'הזמנה',
    refresh: 'רענן',
    loadingOrder: 'טוען פרטי הזמנה...',
    autoUpdating: 'מתעדכן אוטומטית כל 10 שניות',
    orderProgress: 'התקדמות הזמנה',
    currentStatus: 'סטטוס נוכחי',
    orderDetails: 'פרטי הזמנה',
    orderDate: 'תאריך הזמנה',
    deliveryType: 'סוג משלוח',
    paymentStatus: 'סטטוס תשלום',
    paymentStatus_pending: 'ממתין',
    paymentStatus_paid: 'שולם',
    paymentStatus_failed: 'נכשל',
    statusPending: 'הזמנה בוצעה',
    statusConfirmed: 'אושרה',
    statusPreparing: 'בהכנה',
    statusReady: 'מוכן לאיסוף/משלוח',
    statusCompleted: 'הושלמה',
    statusCancelled: 'בוטלה',
    orderCancelled: 'הזמנה בוטלה',
    orderCancelledMessage: 'הזמנה זו בוטלה ולא תתמלא.',
    cancelledAt: 'בוטלה ב',

    // API Status
    apiInitialized: 'API אותחל בהצלחה',
    apiInitializing: 'מאתחל API...',
    apiFailed: 'איתחול API נכשל',
    testApiEndpoints: 'בדוק נקודות קצה של API',

    // Development
    developmentStatus: 'סטטוס פיתוח',
    accessAt: 'גש ב',
    currentView: 'תצוגה נוכחית',

    // Branch Selection Modal
    branchModalTitle: 'כבר אכלנו יחד?',
    branchModalLogin: 'התחבר',
    branchModalWelcomeBack: 'ברוך שובך',
    branchModalPickup: 'איסוף עצמי',
    branchModalDelivery: 'משלוח',
    branchModalCity: 'עיר',
    branchModalStreet: 'רחוב',
    branchModalHouseNumber: 'מספר בית',
    branchModalFindingBranch: 'מחפש סניף קרוב...',
    branchModalNotInRange: 'הכתובת אינה באזור משלוחים',
    branchModalSelectBranch: 'בחר סניף',
    branchModalSearchBranches: 'חפש סניף...',
    branchModalWhenArrive: 'מתי תרצה להגיע?',
    branchModalBack: 'חזרה',
    branchModalContinue: 'המשך',
    branchModalNoBranchFound: 'לא נמצא סניף באזור זה',
    branchModalRequiredField: 'שדה חובה',
    branchModalSelectTime: 'בחר זמן',

    // Loyalty Points
    loyaltyPoints: 'נקודות נאמנות',
    yourPoints: 'הנקודות שלך',
    pointsBalance: '{{points}} נקודות',
    pointsYouWillEarn: 'נקודות שתצברו',
    redeemPoints: 'מימוש נקודות',
    usePoints: 'השתמש בנקודות',
    pointsDiscount: 'הנחת נקודות',
    pointsRedeemed: 'נקודות שמומשו',
    maxPointsAvailable: 'מקסימום זמין: {{points}}',
    pointsEarned: 'נקודות שנצברו',
    loginToEarnPoints: 'התחבר כדי לצבור נקודות נאמנות',
    pointsWillBeAwarded: 'נקודות יזוכו כשההזמנה תושלם',
    useAllPoints: 'השתמש בהכל',
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
    backToCart: 'العودة إلى السلة',
    customerInfo: 'معلومات العميل',
    deliveryInfo: 'معلومات التسليم',
    delivery: 'التسليم',
    review: 'مراجعة',
    payment: 'الدفع',
    placeOrder: 'تقديم الطلب',
    orderConfirmation: 'تأكيد الطلب',

    // Customer Info Step
    enterYourDetails: 'الرجاء إدخال معلومات الاتصال الخاصة بك',
    firstName: 'الاسم الأول',
    lastName: 'اسم العائلة',
    enterFirstName: 'أدخل الاسم الأول',
    enterLastName: 'أدخل اسم العائلة',
    phone: 'الهاتف',
    optional: 'اختياري',
    email: 'البريد الإلكتروني',
    invalidPhone: 'رقم هاتف غير صالح',
    invalidEmail: 'عنوان بريد إلكتروني غير صالح',
    phoneUsedForOrderUpdates: 'سنستخدم هذا لإرسال تحديثات الطلب إليك',
    emailForReceipt: 'اختياري: احصل على إيصال الطلب عبر البريد الإلكتروني',
    privacyNotice: 'إشعار الخصوصية',
    guestCheckoutInfo: 'سيتم استخدام معلوماتك فقط لهذا الطلب. نحن نحترم خصوصيتك.',

    // Delivery Step
    deliveryOptions: 'خيارات التسليم',
    selectDeliveryMethod: 'كيف تريد استلام طلبك؟',
    pickup: 'الاستلام',
    pickupAtBranch: 'الاستلام من الفرع',
    deliverToAddress: 'التوصيل إلى العنوان',
    deliveryAddress: 'عنوان التسليم',
    enterFullAddress: 'أدخل عنوان التسليم الكامل',
    includeStreetCityApt: 'تضمين الشارع والمدينة ورقم الشقة',
    calculatingDeliveryFee: 'حساب رسوم التوصيل',
    addressNotDeliverable: 'العنوان خارج منطقة التوصيل',
    failedToCalculateFee: 'فشل حساب رسوم التوصيل',
    freeDelivery: 'توصيل مجاني',
    orderAboveFreeThreshold: 'طلبك مؤهل للتوصيل المجاني (الحد الأدنى ₪{{threshold}})',
    deliveryFee: 'رسوم التوصيل',
    freeDeliveryAt: 'توصيل مجاني للطلبات التي تزيد عن ₪{{threshold}}',
    deliveryTime: 'وقت التسليم',
    pickupTime: 'وقت الاستلام',
    selectPreferredDeliveryTime: 'حدد وقت التسليم المفضل',
    selectPreferredPickupTime: 'حدد وقت الاستلام المفضل',
    summary: 'ملخص',
    method: 'الطريقة',
    address: 'العنوان',
    time: 'الوقت',
    free: 'مجاني',

    // Review Step
    reviewOrder: 'مراجعة طلبك',
    reviewBeforePayment: 'يرجى مراجعة تفاصيل طلبك قبل المتابعة إلى الدفع',
    name: 'الاسم',
    edit: 'تعديل',
    orderItems: 'عناصر الطلب',
    notes: 'ملاحظات',
    priceBreakdown: 'تفصيل السعر',
    tax: 'ضريبة',
    beforeContinuing: 'قبل المتابعة',
    termsNotice: 'بتقديم هذا الطلب، فإنك توافق على شروطنا وأحكامنا.',

    // Payment Step
    creatingCustomer: 'إنشاء ملف تعريف العميل',
    creatingOrder: 'إنشاء طلبك',
    redirectingToPayment: 'إعادة التوجيه إلى الدفع',
    checkoutFailed: 'فشل الدفع',
    orderConfirmed: 'تم تأكيد الطلب',
    trackingToken: 'رمز التتبع',
    trackYourOrder: 'تتبع طلبك',
    useTokenToTrack: 'استخدم رمز التتبع للتحقق من حالة الطلب في أي وقت',
    confirmationSentTo: 'تم إرسال التأكيد إلى {{phone}}',
    doNotCloseWindow: 'الرجاء عدم إغلاق هذه النافذة',
    saveTrackingToken: 'احفظ هذا الرمز لتتبع طلبك لاحقًا',
    readyToComplete: 'جاهز لإكمال طلبك',
    completeOrder: 'إكمال الطلب',

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
    enterTrackingDetails: 'أدخل رقم الطلب ورمز التتبع لعرض حالة الطلب',
    orderNumberHelp: 'موجود في تأكيد طلبك',
    trackingTokenHelp: 'تم إرساله إليك عبر الرسائل القصيرة أو البريد الإلكتروني بعد تقديم الطلب',
    trackMyOrder: 'تتبع طلبي',
    whereToFind: 'أين يمكنني العثور على هذه المعلومات؟',
    confirmationEmail: 'في بريد تأكيد الطلب الإلكتروني',
    confirmationSMS: 'في رسالة تأكيد الطلب القصيرة',
    checkoutConfirmation: 'في صفحة تأكيد الدفع',
    example: 'مثال',
    enterOrderIdAndToken: 'الرجاء إدخال رقم الطلب ورمز التتبع',
    enterDifferentOrder: 'تتبع طلب مختلف',
    order: 'طلب',
    refresh: 'تحديث',
    loadingOrder: 'جاري تحميل تفاصيل الطلب...',
    autoUpdating: 'التحديث التلقائي كل 10 ثواني',
    orderProgress: 'تقدم الطلب',
    currentStatus: 'الحالة الحالية',
    orderDetails: 'تفاصيل الطلب',
    orderDate: 'تاريخ الطلب',
    deliveryType: 'نوع التسليم',
    paymentStatus: 'حالة الدفع',
    paymentStatus_pending: 'قيد الانتظار',
    paymentStatus_paid: 'مدفوع',
    paymentStatus_failed: 'فشل',
    statusPending: 'تم تقديم الطلب',
    statusConfirmed: 'مؤكد',
    statusPreparing: 'جاري التحضير',
    statusReady: 'جاهز للاستلام/التسليم',
    statusCompleted: 'مكتمل',
    statusCancelled: 'ملغى',
    orderCancelled: 'تم إلغاء الطلب',
    orderCancelledMessage: 'تم إلغاء هذا الطلب ولن يتم تنفيذه.',
    cancelledAt: 'تم الإلغاء في',

    // API Status
    apiInitialized: 'تم تهيئة API بنجاح',
    apiInitializing: 'جاري تهيئة API...',
    apiFailed: 'فشلت تهيئة API',
    testApiEndpoints: 'اختبار نقاط نهاية API',

    // Development
    developmentStatus: 'حالة التطوير',
    accessAt: 'الوصول في',
    currentView: 'العرض الحالي',

    // Branch Selection Modal
    branchModalTitle: 'أكلنا معًا من قبل؟',
    branchModalLogin: 'تسجيل الدخول',
    branchModalWelcomeBack: 'مرحبًا بعودتك',
    branchModalPickup: 'الاستلام',
    branchModalDelivery: 'التوصيل',
    branchModalCity: 'المدينة',
    branchModalStreet: 'الشارع',
    branchModalHouseNumber: 'رقم المنزل',
    branchModalFindingBranch: 'البحث عن أقرب فرع...',
    branchModalNotInRange: 'العنوان ليس في نطاق التوصيل',
    branchModalSelectBranch: 'اختر الفرع',
    branchModalSearchBranches: 'ابحث عن فرع...',
    branchModalWhenArrive: 'متى تريد الوصول؟',
    branchModalBack: 'رجوع',
    branchModalContinue: 'متابعة',
    branchModalNoBranchFound: 'لم يتم العثور على فرع في هذه المنطقة',
    branchModalRequiredField: 'هذا الحقل مطلوب',
    branchModalSelectTime: 'اختر الوقت',

    // Loyalty Points
    loyaltyPoints: 'نقاط الولاء',
    yourPoints: 'نقاطك',
    pointsBalance: '{{points}} نقاط',
    pointsYouWillEarn: 'النقاط التي ستحصل عليها',
    redeemPoints: 'استبدال النقاط',
    usePoints: 'استخدم النقاط',
    pointsDiscount: 'خصم النقاط',
    pointsRedeemed: 'النقاط المستبدلة',
    maxPointsAvailable: 'الحد الأقصى المتاح: {{points}}',
    pointsEarned: 'النقاط المكتسبة',
    loginToEarnPoints: 'سجل الدخول لكسب نقاط الولاء',
    pointsWillBeAwarded: 'سيتم منح النقاط عند اكتمال طلبك',
    useAllPoints: 'استخدم الكل',
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
