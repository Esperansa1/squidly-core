import React from 'react';

const TutorialsContent = () => {
  return (
    <div className="h-full flex flex-col" style={{ backgroundColor: '#F2F2F2' }}>
      <div className="flex-1 p-6 overflow-y-auto">
        <div className="bg-white rounded-lg shadow-sm p-8">
          <h1 className="text-2xl text-gray-800 mb-4" style={{ fontWeight: 800 }}>
            מדריכים והדרכות
          </h1>
          <p className="text-gray-600 mb-8" style={{ fontWeight: 400 }}>
            כאן תמצא מדריכים מפורטים כיצד להשתמש במערכת, וידיאוהדרכה ותשובות לשאלות נפוצות. דף זה יפותח בקרוב.
          </p>
          
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">
            <div className="bg-indigo-50 border border-indigo-100 rounded-lg p-6 text-center">
              <div className="text-3xl mb-3">📚</div>
              <h3 className="text-lg text-indigo-800 mb-2" style={{ fontWeight: 600 }}>
                מדריכים כתובים
              </h3>
              <p className="text-sm text-indigo-600">
                הוראות שלב אחר שלב לכל תכונות המערכת
              </p>
            </div>
            <div className="bg-purple-50 border border-purple-100 rounded-lg p-6 text-center">
              <div className="text-3xl mb-3">🎥</div>
              <h3 className="text-lg text-purple-800 mb-2" style={{ fontWeight: 600 }}>
                סרטוני הדרכה
              </h3>
              <p className="text-sm text-purple-600">
                הדמיות חזותיות של תהליכי עבודה במערכת
              </p>
            </div>
            <div className="bg-green-50 border border-green-100 rounded-lg p-6 text-center">
              <div className="text-3xl mb-3">❓</div>
              <h3 className="text-lg text-green-800 mb-2" style={{ fontWeight: 600 }}>
                שאלות נפוצות
              </h3>
              <p className="text-sm text-green-600">
                תשובות למקרים הנפוצים ופתרונות בעיות
              </p>
            </div>
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="bg-blue-50 border border-blue-100 rounded-lg p-6">
              <h3 className="text-lg text-blue-800 mb-3" style={{ fontWeight: 600 }}>
                התחלה מהירה
              </h3>
              <ul className="text-sm text-blue-700 space-y-2">
                <li>• הגדרת המערכת הראשונית</li>
                <li>• יצירת תפריט ראשון</li>
                <li>• קבלת הזמנה ראשונה</li>
                <li>• ניהול בסיסי של המערכת</li>
              </ul>
            </div>
            <div className="bg-orange-50 border border-orange-100 rounded-lg p-6">
              <h3 className="text-lg text-orange-800 mb-3" style={{ fontWeight: 600 }}>
                תכונות מתקדמות
              </h3>
              <ul className="text-sm text-orange-700 space-y-2">
                <li>• ניתוח דוחות מפורט</li>
                <li>• אוטומציה של תהליכים</li>
                <li>• אינטגרציה עם מערכות חיצוניות</li>
                <li>• התאמות מתקדמות</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default TutorialsContent;