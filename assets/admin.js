(function($){
 'use strict';
 $(function(){
   var $ids = $('#mp-gallery-ids'), $preview = $('#mp-gallery-preview');
   if (!$ids.length) return;
   function render(selection) {
     var ids=[], html='';
     selection.forEach(function(item){
       ids.push(item.id);
       var img = item.sizes && item.sizes.thumbnail ? item.sizes.thumbnail.url : item.url;
       html += '<span data-id="'+Number(item.id)+'"><img src="'+String(img).replace(/"/g,'&quot;')+'" alt=""><button type="button" class="mp-remove-image" aria-label="Fjern billede">×</button></span>';
     });
     $ids.val(ids.join(',')); $preview.html(html);
   }
   $('#mp-gallery-add').on('click',function(e){
     e.preventDefault();
     var frame=wp.media({title:'Vælg billeder til opskriften',button:{text:'Brug billeder'},library:{type:'image'},multiple:true});
     frame.on('open',function(){
       var selected=frame.state().get('selection');
       $ids.val().split(',').filter(Boolean).forEach(function(id){
         var attachment=wp.media.attachment(Number(id)); attachment.fetch(); selected.add(attachment);
       });
     });
     frame.on('select',function(){
       render(frame.state().get('selection').toJSON());
     });
     frame.open();
   });
   $('#mp-gallery-clear').on('click',function(e){e.preventDefault(); $ids.val(''); $preview.empty();});
   $preview.on('click','.mp-remove-image',function(){
     $(this).closest('span').remove();
     $ids.val($preview.find('span[data-id]').map(function(){return $(this).attr('data-id');}).get().join(','));
   });
 });
})(jQuery);
