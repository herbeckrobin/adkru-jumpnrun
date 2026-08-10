/**
 * Editor-Teil des Jump-and-Run-Blocks.
 *
 * Buildless: nutzt die von WordPress ausgelieferten wp.*-Globals, damit das
 * Plugin keine zweite Build-Pipeline neben Vite braucht.
 *
 * Die Controls entstehen aus window.JumpnrunBlockSchema, das PHP aus
 * GameAttributes spiegelt. Ein neues Attribut taucht hier also automatisch
 * auf, ohne dass diese Datei angefasst werden muss.
 */
((wp) => {
  if (!wp?.blocks || !wp.element) {
    return;
  }

  const el = wp.element.createElement;
  const { useBlockProps, InspectorControls } = wp.blockEditor;
  const { PanelBody, TextControl } = wp.components;
  const ServerSideRender = wp.serverSideRender;
  const { __ } = wp.i18n;

  const schema = window.JumpnrunBlockSchema || {};

  const controlsFor = (attributes, setAttributes) =>
    Object.keys(schema).map((key) => {
      const field = schema[key];

      return el(TextControl, {
        key,
        label: field.label,
        help: field.description,
        value: attributes[key] || '',
        placeholder: __('Aus den Einstellungen', 'jumpnrun'),
        type: field.type === 'number' ? 'number' : 'text',
        onChange: (value) => setAttributes({ [key]: value }),
      });
    });

  wp.blocks.registerBlockType('jumpnrun/game', {
    edit: (props) => {
      const blockProps = useBlockProps();

      const preview = ServerSideRender
        ? el(ServerSideRender, {
            block: 'jumpnrun/game',
            attributes: props.attributes,
          })
        : el('p', {}, __('Vorschau nicht verfügbar.', 'jumpnrun'));

      return el(
        'div',
        blockProps,
        el(
          InspectorControls,
          {},
          el(
            PanelBody,
            { title: __('Spiel', 'jumpnrun'), initialOpen: true },
            controlsFor(props.attributes, props.setAttributes),
          ),
        ),
        // Klicks gehen an den Block, nicht in die Vorschau. Sonst
        // laesst sich der Block im Editor nur schwer selektieren.
        el('div', { style: { pointerEvents: 'none' } }, preview),
      );
    },

    // Dynamischer Block: das Markup baut PHP bei jedem Aufruf.
    save: () => null,
  });
})(window.wp);
