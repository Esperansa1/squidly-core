import { useState, useEffect } from 'react';
import TabSelector from './ui/TabSelector.jsx';
import ProfileSection from './settings/ProfileSection.jsx';
import UserManagement from './settings/UserManagement.jsx';
import api from '../services/api.js';

const Settings = () => {
  const [activeTab, setActiveTab] = useState('הפרופיל שלי');
  const [currentUser, setCurrentUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadCurrentUser();
  }, []);

  const loadCurrentUser = async () => {
    try {
      setLoading(true);
      const user = await api.getCurrentUser();
      setCurrentUser(user);
    } catch (error) {
      console.error('Error loading current user:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="h-full flex flex-col" dir="rtl">
      <div className="flex-shrink-0 px-6 pt-6">
        <TabSelector
          tabs={['הפרופיל שלי', 'ניהול משתמשים']}
          activeTab={activeTab}
          onTabChange={setActiveTab}
        />
      </div>

      <div className="flex-1 h-0 px-6 pb-6 overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center h-full">
            <div className="text-gray-500">טוען...</div>
          </div>
        ) : (
          <>
            {activeTab === 'הפרופיל שלי' && (
              <ProfileSection
                currentUser={currentUser}
                onUpdate={loadCurrentUser}
              />
            )}
            {activeTab === 'ניהול משתמשים' && (
              <UserManagement />
            )}
          </>
        )}
      </div>
    </div>
  );
};

export default Settings;
