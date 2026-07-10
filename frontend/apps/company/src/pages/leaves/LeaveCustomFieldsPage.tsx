import React from 'react';
import ModuleCustomFieldsPage from '../shared/ModuleCustomFieldsPage';

const LeaveCustomFieldsPage: React.FC = () => {
  return (
    <ModuleCustomFieldsPage
      entityType="leave_request"
      moduleLabel="İzin Yönetimi"
      backPath="/leaves"
      moduleColor="#06b6d4"
    />
  );
};

export default LeaveCustomFieldsPage;

