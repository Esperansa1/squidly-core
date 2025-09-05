import React from 'react';
import AppLayout from '../AppLayout.jsx';

const SettingsPage = () => {
  return (
    <AppLayout activeNavItem="הגדרות">
      <div className="p-6" style={{ backgroundColor: '#F2F2F2' }}>
        <div className="max-w-7xl mx-auto">
          <div className="bg-white rounded-lg shadow-sm p-8">
            <h1 className="text-2xl text-gray-800 mb-4" style={{ fontWeight: 800 }}>
              הגדרות מערכת
            </h1>
            <p className="text-gray-600 mb-8" style={{ fontWeight: 400 }}>
              כאן תוכל להתאים את הגדרות המערכת לצרכי המסעדה שלך. דף זה יפותח בקרוב.
            </p>
            
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <div className="bg-gray-50 rounded-lg p-6">
                <h3 className="text-lg text-gray-800 mb-4" style={{ fontWeight: 600 }}>
                  הגדרות כלליות
                </h3>
                <div className="space-y-4">
                  <div className="flex justify-between items-center py-2">
                    <span className="text-gray-700">שם המסעדה</span>
                    <span className="text-gray-500">לא הוגדר</span>
                  </div>
                  <div className="flex justify-between items-center py-2">
                    <span className="text-gray-700">כתובת</span>
                    <span className="text-gray-500">לא הוגדר</span>
                  </div>
                  <div className="flex justify-between items-center py-2">
                    <span className="text-gray-700">טלפון</span>
                    <span className="text-gray-500">לא הוגדר</span>
                  </div>
                  <div className="flex justify-between items-center py-2">
                    <span className="text-gray-700">שעות פעילות</span>
                    <span className="text-gray-500">לא הוגדר</span>
                  </div>
                </div>
              </div>
              
              <div className="bg-gray-50 rounded-lg p-6">
                <h3 className="text-lg text-gray-800 mb-4" style={{ fontWeight: 600 }}>
                  הגדרות תשלום
                </h3>
                <div className="space-y-4">
                  <div className="flex justify-between items-center py-2">
                    <span className="text-gray-700">מטבע</span>
                    <span className="text-gray-800">שקל (₪)</span>
                  </div>
                  <div className="flex justify-between items-center py-2">
                    <span className="text-gray-700">מע"מ</span>
                    <span className="text-gray-500">17%</span>
                  </div>
                  <div className="flex justify-between items-center py-2">
                    <span className="text-gray-700">דמי משלוח</span>
                    <span className="text-gray-500">לא הוגדר</span>
                  </div>
                  <div className="flex justify-between items-center py-2">
                    <span className="text-gray-700">הזמנה מינימלית</span>
                    <span className="text-gray-500">לא הוגדר</span>
                  </div>
                </div>
              </div>
              
              <div className="bg-gray-50 rounded-lg p-6">
                <h3 className="text-lg text-gray-800 mb-4" style={{ fontWeight: 600 }}>
                  הגדרות התראות
                </h3>
                <div className="space-y-4">
                  <div className="flex justify-between items-center py-2">
                    <span className="text-gray-700">התראות הזמנות חדשות</span>
                    <div className="w-10 h-6 bg-gray-300 rounded-full relative">
                      <div className="w-4 h-4 bg-white rounded-full absolute top-1 left-1"></div>
                    </div>
                  </div>
                  <div className="flex justify-between items-center py-2">
                    <span className="text-gray-700">התראות תשלום</span>
                    <div className="w-10 h-6 bg-gray-300 rounded-full relative">
                      <div className="w-4 h-4 bg-white rounded-full absolute top-1 left-1"></div>
                    </div>
                  </div>
                  <div className="flex justify-between items-center py-2">
                    <span className="text-gray-700">התראות מלאי</span>
                    <div className="w-10 h-6 bg-gray-300 rounded-full relative">
                      <div className="w-4 h-4 bg-white rounded-full absolute top-1 left-1"></div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div className="bg-gray-50 rounded-lg p-6">
                <h3 className="text-lg text-gray-800 mb-4" style={{ fontWeight: 600 }}>
                  הגדרות גיבוי
                </h3>
                <div className="space-y-4">
                  <div className="flex justify-between items-center py-2">
                    <span className="text-gray-700">גיבוי אוטומטי</span>
                    <div className="w-10 h-6 bg-green-500 rounded-full relative">
                      <div className="w-4 h-4 bg-white rounded-full absolute top-1 right-1"></div>
                    </div>
                  </div>
                  <div className="flex justify-between items-center py-2">
                    <span className="text-gray-700">תדירות גיבוי</span>
                    <span className="text-gray-800">יומי</span>
                  </div>
                  <div className="flex justify-between items-center py-2">
                    <span className="text-gray-700">גיבוי אחרון</span>
                    <span className="text-gray-500">אף פעם</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
};

export default SettingsPage;