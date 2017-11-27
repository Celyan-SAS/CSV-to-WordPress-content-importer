(function($) {

    $(document).ready(function() {
        //listener
        $('[id*="wac_edit_save"]').click(function(){
            wac_edit_save(this);
        });
        
        //listener
        $('[id*="wac_delete_save"]').click(function(){
            wac_delete_save(this);
        });
        
        //listener
        //$('#wac_processfile').click(function(){
        $('[id*="wac_processfile"]').click(function(){
            var idinput = $(this).attr('data-input');
            $('#wac_processfile_input'+idinput).trigger('click');
            $('#wac_processfile_input'+idinput).change(function(){
                $('#wac_processfile_button'+idinput).show();
                
            });
        });
        
    });
    
    function wac_edit_save(element){
        var data = {
            "action": "wac_editcsvdocument",
            "wacdoc": $(element).attr('data-li'),
        };
        
        $.post(ajaxurl, data, function(theajaxresponse) {
            $('#html_admin_assoc_cpt').html(theajaxresponse);
        })
        .fail(function() {
            console.log( "error javascript wac_delete_save" );
        });
    }
    
    function wac_delete_save(element){
        var data = {
            "action": "wac_deletecsvdocument",
            "wacdoc": $(element).attr('data-li'),
        };
        
        $.post(ajaxurl, data, function(theajaxresponse) {
            var target = $(element).attr('data-li');
            $('#wac_'+target).hide();
            $('#html_admin_assoc_cpt').html('');
        })
        .fail(function() {
            console.log( "error javascript wac_delete_save" );
        });
    }
    
})( jQuery );