import { __ } from "@wordpress/i18n";
import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import { PanelBody, ToggleControl } from "@wordpress/components";

export default function Edit({ attributes, setAttributes }) {
  const blockProps = useBlockProps();

  return (
    <div {...blockProps}>
      <InspectorControls>
        <PanelBody
          title={__("Lexikon Optionen", "lexikonmanager")}
          initialOpen={true}
        >
          <ToggleControl
            label={__("Suche anzeigen", "lexikonmanager")}
            checked={attributes.show_search}
            onChange={(value) => setAttributes({ show_search: value })}
          />
          <ToggleControl
            label={__("Tabs anzeigen", "lexikonmanager")}
            checked={attributes.show_tabs}
            onChange={(value) => setAttributes({ show_tabs: value })}
          />
        </PanelBody>
      </InspectorControls>
      <div className="lm-lexikon-block-preview">
        <h3>{__("Lexikon Vorschau", "lexikonmanager")}</h3>
        <p>
          {__(
            "Der Inhalt wird im Frontend serverseitig generiert.",
            "lexikonmanager",
          )}
        </p>
        <ul>
          {attributes.show_search && (
            <li>{__("Suche: aktiviert", "lexikonmanager")}</li>
          )}
          {!attributes.show_search && (
            <li>{__("Suche: deaktiviert", "lexikonmanager")}</li>
          )}
          {attributes.show_tabs && (
            <li>{__("Tabs: aktiviert", "lexikonmanager")}</li>
          )}
          {!attributes.show_tabs && (
            <li>{__("Tabs: deaktiviert", "lexikonmanager")}</li>
          )}
        </ul>
      </div>
    </div>
  );
}
