import React from 'react';
import ModuleCustomFieldsPage from '../shared/ModuleCustomFieldsPage';

const DocumentCustomFieldsPage: React.FC = () => {
  return (
    <ModuleCustomFieldsPage
      entityType="document"
      moduleLabel="Evrak Yönetimi"
      backPath="/documents"
      moduleColor="#8b5cf6"
    />
  );
};

export default DocumentCustomFieldsPage;

