(function(wp){
  const { registerBlockType } = wp.blocks;
  const { createElement: el } = wp.element;
  const { __ } = wp.i18n;
  const { useSelect, dispatch } = wp.data;
  const blockEditor = wp.blockEditor || wp.editor;

  // بلاک سرور-رندر: خروجی واقعی در فرانت با شورتکد/کال‌بک PHP رندر می‌شود
  registerBlockType('irp/inline-related', {
    title: IRP_BLOCK.title || 'Inline Related Posts',
    description: IRP_BLOCK.description || 'Insert related posts box inline.',
    icon: IRP_BLOCK.icon || 'admin-post',
    category: 'widgets',
    supports: { html: false },
    edit: function(){
      return el('div', { className: 'irp-block-placeholder', style:{
        border:'1px dashed #cbd5e1', padding:'12px', borderRadius:'8px', background:'#f8fafc'
      }}, '➕ باکس مطالب مرتبط اینجا درج خواهد شد');
    },
    save: function(){ return null; }
  });

  // اگر کاربر بلاک را اضافه کند، ما یک شورتکد هم در محتوا قرار نمی‌دهیم
  // چون بلاک سرور-رندر از render_callback استفاده می‌کند.

  // یک اکشن کمکی: اگر نیاز بود با کلیک، یک بلاک درج کنیم:
  // می‌تونی این رو با UI سفارشی یا منوی ادیتور وصل کنی.
  function insertIRPBlock() {
    const block = wp.blocks.createBlock('irp/inline-related');
    dispatch('core/block-editor').insertBlocks(block);
  }

  // می‌تونی با یک کلید میانبر ساده هم اضافه کنی (اختیاری):
  document.addEventListener('keydown', function(e){
    if (e.altKey && e.shiftKey && e.key.toLowerCase() === 'i') {
      try { insertIRPBlock(); } catch(err){}
    }
  });

})(window.wp);
