import React from 'react';
import AppLayout from '../AppLayout.jsx';

const ManagementAreaPage = () => {
  return (
    <AppLayout activeNavItem="איזור ניהול">
      <div className="p-6" style={{ backgroundColor: '#F2F2F2' }}>
        <div className="max-w-7xl mx-auto">
          <div className="bg-white rounded-lg shadow-sm p-8">
            <h1 className="text-2xl text-gray-800 mb-4" style={{ fontWeight: 800 }}>
              איזור ניהול
            </h1>
            <p className="text-gray-600" style={{ fontWeight: 400 }}>
              כאן תוכל לנהל את כל המערכות המרכזיות של המסעדה. דף זה יפותח בקרוב.
            </p>
            <div className="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div className="bg-gray-50 rounded-lg p-4">
                <h3 className="text-lg text-gray-800 mb-2" style={{ fontWeight: 600 }}>
                  ניהול כללי
                </h3>
                <p className="text-sm text-gray-600">תצוגה כללית של המסעדה</p>
              </div>
              <div className="bg-gray-50 rounded-lg p-4">
                <h3 className="text-lg text-gray-800 mb-2" style={{ fontWeight: 600 }}>
                  הגדרות מהירות
                </h3>
                <p className="text-sm text-gray-600">הגדרות בסיסיות למסעדה</p>
              </div>
              <div className="bg-gray-50 rounded-lg p-4">
                <h3 className="text-lg text-gray-800 mb-2" style={{ fontWeight: 600 }}>
                  דוחות יומיים
                </h3>
                <p className="text-sm text-gray-600">סיכום פעילות יומית</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
};

export default ManagementAreaPage;