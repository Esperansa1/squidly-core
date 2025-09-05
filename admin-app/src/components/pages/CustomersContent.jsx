import React from 'react';

const CustomersContent = () => {
  return (
    <div className="h-full flex flex-col" style={{ backgroundColor: '#F2F2F2' }}>
      <div className="flex-1 p-6 overflow-y-auto">
        <div className="bg-white rounded-lg shadow-sm p-8">
          <h1 className="text-2xl text-gray-800 mb-4" style={{ fontWeight: 800 }}>
            ניהול לקוחות
          </h1>
          <p className="text-gray-600 mb-8" style={{ fontWeight: 400 }}>
            כאן תוכל לנהל את בסיס הלקוחות שלך, לעקוב אחרי הזמנות ולנהל תוכניות נאמנות. דף זה יפותח בקרוב.
          </p>
          
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
            <div className="bg-teal-50 border border-teal-100 rounded-lg p-4">
              <h3 className="text-sm text-teal-600 mb-1" style={{ fontWeight: 600 }}>
                סך לקוחות
              </h3>
              <p className="text-2xl text-teal-700" style={{ fontWeight: 800 }}>
                0
              </p>
            </div>
            <div className="bg-pink-50 border border-pink-100 rounded-lg p-4">
              <h3 className="text-sm text-pink-600 mb-1" style={{ fontWeight: 600 }}>
                לקוחות חדשים השבוע
              </h3>
              <p className="text-2xl text-pink-700" style={{ fontWeight: 800 }}>
                0
              </p>
            </div>
            <div className="bg-cyan-50 border border-cyan-100 rounded-lg p-4">
              <h3 className="text-sm text-cyan-600 mb-1" style={{ fontWeight: 600 }}>
                לקוחות פעילים
              </h3>
              <p className="text-2xl text-cyan-700" style={{ fontWeight: 800 }}>
                0
              </p>
            </div>
            <div className="bg-emerald-50 border border-emerald-100 rounded-lg p-4">
              <h3 className="text-sm text-emerald-600 mb-1" style={{ fontWeight: 600 }}>
                נקודות נאמנות
              </h3>
              <p className="text-2xl text-emerald-700" style={{ fontWeight: 800 }}>
                0
              </p>
            </div>
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="bg-gray-50 rounded-lg p-4">
              <h3 className="text-lg text-gray-800 mb-2" style={{ fontWeight: 600 }}>
                מאגר לקוחות
              </h3>
              <p className="text-sm text-gray-600">רשימה מפורטת של כל הלקוחות והזמנותיהם</p>
            </div>
            <div className="bg-gray-50 rounded-lg p-4">
              <h3 className="text-lg text-gray-800 mb-2" style={{ fontWeight: 600 }}>
                תכנית נאמנות
              </h3>
              <p className="text-sm text-gray-600">ניהול נקודות והטבות ללקוחות נאמנים</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default CustomersContent;