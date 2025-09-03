import React from 'react';
import AppLayout from '../AppLayout.jsx';

const TutorialsPage = () => {
  return (
    <AppLayout activeNavItem="סרטוני הדרכה">
      <div className="p-6" style={{ backgroundColor: '#F2F2F2' }}>
        <div className="max-w-7xl mx-auto">
          <div className="bg-white rounded-lg shadow-sm p-8">
            <h1 className="text-2xl text-gray-800 mb-4" style={{ fontWeight: 800 }}>
              סרטוני הדרכה
            </h1>
            <p className="text-gray-600 mb-8" style={{ fontWeight: 400 }}>
              כאן תמצא סרטוני הדרכה לשימוש במערכת, טיפים ועצות לניהול המסעדה. דף זה יפותח בקרוב.
            </p>
            
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div className="bg-gray-50 rounded-lg p-6 border border-gray-200">
                <div className="w-full h-32 bg-gray-200 rounded mb-4 flex items-center justify-center">
                  <div className="text-4xl text-gray-400">🎥</div>
                </div>
                <h3 className="text-lg text-gray-800 mb-2" style={{ fontWeight: 600 }}>
                  תחילת העבודה
                </h3>
                <p className="text-sm text-gray-600 mb-3">
                  למד כיצד להתחיל להשתמש במערכת לניהול המסעדה
                </p>
                <div className="text-xs text-gray-500">משך: 5 דקות</div>
              </div>
              
              <div className="bg-gray-50 rounded-lg p-6 border border-gray-200">
                <div className="w-full h-32 bg-gray-200 rounded mb-4 flex items-center justify-center">
                  <div className="text-4xl text-gray-400">📋</div>
                </div>
                <h3 className="text-lg text-gray-800 mb-2" style={{ fontWeight: 600 }}>
                  ניהול תפריט
                </h3>
                <p className="text-sm text-gray-600 mb-3">
                  הדרכה מפורטת על יצירה ועריכה של פריטי תפריט
                </p>
                <div className="text-xs text-gray-500">משך: 8 דקות</div>
              </div>
              
              <div className="bg-gray-50 rounded-lg p-6 border border-gray-200">
                <div className="w-full h-32 bg-gray-200 rounded mb-4 flex items-center justify-center">
                  <div className="text-4xl text-gray-400">💳</div>
                </div>
                <h3 className="text-lg text-gray-800 mb-2" style={{ fontWeight: 600 }}>
                  ניהול תשלומים
                </h3>
                <p className="text-sm text-gray-600 mb-3">
                  כיצד להגדיר ולנהל שיטות תשלום שונות
                </p>
                <div className="text-xs text-gray-500">משך: 6 דקות</div>
              </div>
              
              <div className="bg-gray-50 rounded-lg p-6 border border-gray-200">
                <div className="w-full h-32 bg-gray-200 rounded mb-4 flex items-center justify-center">
                  <div className="text-4xl text-gray-400">📊</div>
                </div>
                <h3 className="text-lg text-gray-800 mb-2" style={{ fontWeight: 600 }}>
                  דוחות וניתוחים
                </h3>
                <p className="text-sm text-gray-600 mb-3">
                  כיצד להפיק דוחות ולנתח את ביצועי המסעדה
                </p>
                <div className="text-xs text-gray-500">משך: 10 דקות</div>
              </div>
              
              <div className="bg-gray-50 rounded-lg p-6 border border-gray-200">
                <div className="w-full h-32 bg-gray-200 rounded mb-4 flex items-center justify-center">
                  <div className="text-4xl text-gray-400">👥</div>
                </div>
                <h3 className="text-lg text-gray-800 mb-2" style={{ fontWeight: 600 }}>
                  ניהול צוות
                </h3>
                <p className="text-sm text-gray-600 mb-3">
                  הוספת עובדים והגדרת הרשאות במערכת
                </p>
                <div className="text-xs text-gray-500">משך: 7 דקות</div>
              </div>
              
              <div className="bg-gray-50 rounded-lg p-6 border border-gray-200">
                <div className="w-full h-32 bg-gray-200 rounded mb-4 flex items-center justify-center">
                  <div className="text-4xl text-gray-400">⚙️</div>
                </div>
                <h3 className="text-lg text-gray-800 mb-2" style={{ fontWeight: 600 }}>
                  הגדרות מערכת
                </h3>
                <p className="text-sm text-gray-600 mb-3">
                  התאמה אישית של המערכת לצרכי המסעדה שלך
                </p>
                <div className="text-xs text-gray-500">משך: 12 דקות</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
};

export default TutorialsPage;