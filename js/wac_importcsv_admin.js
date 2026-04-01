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
        
        //listener - bouton "Fichier..."
        $('[id^="wac_processfile"]').not('[id*="_input"]').not('[id*="_button"]').click(function(){
            var idinput = $(this).attr('data-input');
            $('#wac_processfile_input'+idinput).trigger('click');
            $('#wac_processfile_input'+idinput).off('change').change(function(){
                $('#wac_processfile_button'+idinput).show();
                wac_analyzeEncoding(this, idinput);
            });
        });
        
    });

    /**
     * Analyse l'encodage du fichier CSV sélectionné et affiche le résultat
     * dans le div #wac_encoding_result{idinput} du TD correspondant.
     */
    function wac_analyzeEncoding(inputElement, idinput) {
        var file = inputElement.files[0];
        if (!file) return;

        var resultDiv = $('#wac_encoding_result' + idinput);
        resultDiv.html('<em>Analyse en cours...</em>');

        var reader = new FileReader();
        reader.readAsArrayBuffer(file);

        reader.onload = function(e) {
            var buffer = e.target.result;
            var bytes = new Uint8Array(buffer);

            // Tentative de décodage en UTF-8 strict (fatal:true = erreur si invalide)
            var isUtf8 = true;
            var decodedUtf8 = '';
            try {
                var decoder = new TextDecoder('utf-8', { fatal: true });
                decodedUtf8 = decoder.decode(buffer);
            } catch (err) {
                isUtf8 = false;
            }

            // Décodage en ISO-8859-1 (toujours possible, sert de fallback)
            var decoderIso = new TextDecoder('iso-8859-1');
            var decodedIso = decoderIso.decode(buffer);

            // Texte effectivement interprété par le système
            var systemText = isUtf8 ? decodedUtf8 : decodedIso;

            // Recherche des caractères accentués dans le texte décodé
            var accentRegex = /[À-ÖØ-öø-ÿ]/g;
            var accentsFound = [];
            var match;
            while ((match = accentRegex.exec(systemText)) !== null) {
                if (accentsFound.indexOf(match[0]) === -1) {
                    accentsFound.push(match[0]);
                }
                if (accentsFound.length >= 20) break; // limiter l'affichage
            }

            // Construction du message
            var encodingDetected = isUtf8 ? 'UTF-8' : 'ISO-8859-1';
            
            // Mise à jour du champ caché pour le PHP
            $('#wac_encoding_input' + idinput).val(encodingDetected);

            var html = '<div class="wac-encoding-report">';

            if (isUtf8) {
                html += '<span style="color:#2a7a2a;font-weight:bold;">✔ Encodage détecté : UTF-8</span><br>';
            } else {
                html += '<span style="color:#c0392b;font-weight:bold;">⚠ Encodage détecté : Non-UTF-8 </span><br>';
            }

            html += '<br><strong>Caractères accentués trouvés :</strong> ';
            if (accentsFound.length > 0) {
                html += '<span style="font-family:monospace;background:#f5f5f5;padding:2px 6px;border-radius:3px;">' + accentsFound.join(' ') + '</span>';
            } else {
                html += '<em>Aucun caractère accentué trouvé.</em>';
            }

            html += '<br><small style="color:#888;">Fichier : ' + $('<span>').text(file.name).html() + ' — Taille : ' + (file.size / 1024).toFixed(1) + ' Ko</small>';
            html += '</div>';

            resultDiv.html(html);
        };

        reader.onerror = function() {
            resultDiv.html('<span style="color:red;">Erreur lors de la lecture du fichier.</span>');
        };
    }
    
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
            //var target = $(element).attr('data-li');
            //$('#wac_'+target).hide();
			$(element).closest('tr').hide();			
            $('#html_admin_assoc_cpt').html('');
        })
        .fail(function() {
            console.log( "error javascript wac_delete_save" );
        });
    }
    
})( jQuery );