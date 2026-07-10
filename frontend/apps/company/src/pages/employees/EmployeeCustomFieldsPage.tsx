import React from 'react';
import ModuleCustomFieldsPage from '../shared/ModuleCustomFieldsPage';

const EmployeeCustomFieldsPage: React.FC = () => {
  return (
    <ModuleCustomFieldsPage
      entityType="employee"
      moduleLabel="Personel"
      backPath="/employees"
      moduleColor="#22c55e"
    />
  );
};

export default EmployeeCustomFieldsPage;

