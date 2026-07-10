import React from 'react';

interface TextNoteWidgetProps {
  content: string;
}

const TextNoteWidget: React.FC<TextNoteWidgetProps> = ({ content }) => {
  if (!content) {
    return (
      <div className="text-note-widget empty">
        <span className="placeholder">Metin içeriği ekleyin...</span>
      </div>
    );
  }

  return (
    <div className="text-note-widget">
      <div className="text-content" dangerouslySetInnerHTML={{ __html: content.replace(/\n/g, '<br/>') }} />
    </div>
  );
};

export default TextNoteWidget;

