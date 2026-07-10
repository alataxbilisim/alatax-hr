import React from 'react';
import ModuleCustomFieldsPage from '../shared/ModuleCustomFieldsPage';

const AssetCustomFieldsPage: React.FC = () => {
  return (
    <ModuleCustomFieldsPage
      entityType="asset"
      moduleLabel="Varlık Yönetimi"
      backPath="/assets"
      moduleColor="#64748b"
    />
  );
};

export default AssetCustomFieldsPage;

