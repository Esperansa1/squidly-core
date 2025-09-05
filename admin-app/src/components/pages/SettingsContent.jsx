import React from 'react';

const SettingsContent = () => {
  return (
    <div className="h-full flex flex-col" style={{ backgroundColor: '#F2F2F2' }}>
      <div className="flex-1 p-6 overflow-y-auto">
        <div className="bg-white rounded-lg shadow-sm p-8">
          <h1 className="text-2xl text-gray-800 mb-4" style={{ fontWeight: 800 }}>
            הגדרות מערכת
          </h1>
          <p className="text-gray-600 mb-8" style={{ fontWeight: 400 }}>
            כאן תוכל לנהל את כל ההגדרות של המערכת, להתאים את הממשק ולקבוע העדפות אישיות. דף זה יפותח בקרוב.
          </p>
          
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">
            <div className="bg-slate-50 border border-slate-100 rounded-lg p-6">
              <h3 className="text-lg text-slate-800 mb-2" style={{ fontWeight: 600 }}>
                הגדרות כלליות
              </h3>
              <p className="text-sm text-slate-600">שם מסעדה, כתובת ופרטים בסיסיים</p>
            </div>
            <div className="bg-rose-50 border border-rose-100 rounded-lg p-6">
              <h3 className="text-lg text-rose-800 mb-2" style={{ fontWeight: 600 }}>
                הגדרות תשלום
              </h3>
              <p className="text-sm text-rose-600">אמצעי תשלום, עמלות וחיבורי API</p>
            </div>
            <div className="bg-amber-50 border border-amber-100 rounded-lg p-6">
              <h3 className="text-lg text-amber-800 mb-2" style={{ fontWeight: 600 }}>
                הגדרות הזמנות
              </h3>
              <p className="text-sm text-amber-600">זמני הכנה, שעות פעילות ומגבלות</p>
            </div>
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div className="bg-emerald-50 border border-emerald-100 rounded-lg p-6">
              <h3 className="text-lg text-emerald-800 mb-2" style={{ fontWeight: 600 }}>
                הגדרות התראות
              </h3>
              <p className="text-sm text-emerald-600">התראות אימייל, SMS והתראות במערכת</p>
            </div>
            <div className="bg-sky-50 border border-sky-100 rounded-lg p-6">
              <h3 className="text-lg text-sky-800 mb-2" style={{ fontWeight: 600 }}>
                הגדרות משתמשים
              </h3>
              <p className="text-sm text-sky-600">הרשאות, תפקידים וגישה למערכת</p>
            </div>
          </div>

          <div className="bg-gray-50 rounded-lg p-6">
            <h3 className="text-lg text-gray-800 mb-4" style={{ fontWeight: 600 }}>
              גיבוי ושחזור
            </h3>
            <p className="text-gray-600 mb-4">
              נהל גיבויים אוטומטיים של נתוני המערכת ושחזור במקרה הצורך.
            </p>
            <div className="flex gap-4">
              <button className="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">
                יצירת גיבוי
              </button>
              <button className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                שחזור מגיבוי
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default SettingsContent;