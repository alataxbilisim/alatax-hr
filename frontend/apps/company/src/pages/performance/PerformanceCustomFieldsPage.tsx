import React from 'react';
import ModuleCustomFieldsPage from '../shared/ModuleCustomFieldsPage';

const PerformanceCustomFieldsPage: React.FC = () => {
  return (
    <ModuleCustomFieldsPage
      entityType="performance"
      moduleLabel="Performans Yönetimi"
      backPath="/performance"
      moduleColor="#14b8a6"
    />
  );
};

export default PerformanceCustomFieldsPage;

