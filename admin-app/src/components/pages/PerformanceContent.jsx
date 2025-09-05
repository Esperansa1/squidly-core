import React from 'react';

const PerformanceContent = () => {
  return (
    <div className="h-full flex flex-col" style={{ backgroundColor: '#F2F2F2' }}>
      <div className="flex-1 p-6 overflow-y-auto">
        <div className="bg-white rounded-lg shadow-sm p-8">
          <h1 className="text-2xl text-gray-800 mb-4" style={{ fontWeight: 800 }}>
            מעקב ביצועים
          </h1>
          <p className="text-gray-600 mb-8" style={{ fontWeight: 400 }}>
            כאן תוכל לעקוב אחרי ביצועי המסעדה, מכירות ונתונים סטטיסטיים. דף זה יפותח בקרוב.
          </p>
          
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
            <div className="bg-red-50 border border-red-100 rounded-lg p-4">
              <h3 className="text-sm text-red-600 mb-1" style={{ fontWeight: 600 }}>
                מכירות היום
              </h3>
              <p className="text-2xl text-red-700" style={{ fontWeight: 800 }}>
                ₪0
              </p>
            </div>
            <div className="bg-green-50 border border-green-100 rounded-lg p-4">
              <h3 className="text-sm text-green-600 mb-1" style={{ fontWeight: 600 }}>
                הזמנות היום
              </h3>
              <p className="text-2xl text-green-700" style={{ fontWeight: 800 }}>
                0
              </p>
            </div>
            <div className="bg-blue-50 border border-blue-100 rounded-lg p-4">
              <h3 className="text-sm text-blue-600 mb-1" style={{ fontWeight: 600 }}>
                לקוחות פעילים
              </h3>
              <p className="text-2xl text-blue-700" style={{ fontWeight: 800 }}>
                0
              </p>
            </div>
            <div className="bg-purple-50 border border-purple-100 rounded-lg p-4">
              <h3 className="text-sm text-purple-600 mb-1" style={{ fontWeight: 600 }}>
                דירוג ממוצע
              </h3>
              <p className="text-2xl text-purple-700" style={{ fontWeight: 800 }}>
                0.0
              </p>
            </div>
          </div>
          
          <div className="bg-gray-50 rounded-lg p-6">
            <h3 className="text-lg text-gray-800 mb-4" style={{ fontWeight: 600 }}>
              גרפים וניתוחים
            </h3>
            <p className="text-gray-600">
              כאן יוצגו גרפים מפורטים של הביצועים, מגמות מכירות וניתוחי נתונים.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PerformanceContent;