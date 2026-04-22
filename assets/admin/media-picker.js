/**
 * jumpnrun Media-Picker
 *
 * Bindet den WP-Media-Frame an alle .jnr-attachment-picker Container.
 * - "Bild wählen": öffnet Media-Library, schreibt Attachment-ID ins Hidden-Input
 *   und tauscht die Vorschau live aus.
 * - "Entfernen": setzt Hidden-Input auf 0, blendet Vorschau aus und submittet
 *   die umgebende Form direkt — sonst hätte der User das Gefühl, der Klick
 *   tut nichts (er müsste danach noch "Speichern" drücken).
 */
(function ($) {
  'use strict';

  function init() {
    if (!window.wp || !window.wp.media) return;

    $(document).on('click', '[data-jnr-picker-select]', function (e) {
      e.preventDefault();
      var $btn = $(this);
      var $wrap = $btn.closest('.jnr-attachment-picker');
      var $input = $wrap.find('[data-jnr-picker-input]');
      var $preview = $wrap.find('.jnr-attachment-preview');

      var frame = wp.media({
        title: 'Bild wählen',
        button: { text: 'Verwenden' },
        library: { type: 'image' },
        multiple: false,
      });

      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        $input.val(attachment.id);
        var url =
          (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) ||
          attachment.url;
        $preview
          .attr('src', url)
          .css({
            'max-width': '80px',
            height: 'auto',
            'border-radius': '4px',
            display: 'block',
          });
      });

      frame.open();
    });

    // Entfernen ist ein echter Submit-Button (type="submit" mit
     // name="jnr_clear[<key>]") — der Browser sendet die Form nativ und der
     // Server setzt den Slot auf 0. Wir machen hier nur sofortiges optisches
     // Feedback (Vorschau ausblenden), Submit selbst NICHT preventDefault.
    $(document).on('click', '[data-jnr-picker-clear]', function () {
      var $btn = $(this);
      var $wrap = $btn.closest('.jnr-attachment-picker');
      var input = $wrap.find('[data-jnr-picker-input]')[0];
      if (input) {
        input.value = '0';
      }
      $wrap.find('.jnr-attachment-preview').hide().attr('src', '');
    });
  }

  $(init);
})(jQuery);
