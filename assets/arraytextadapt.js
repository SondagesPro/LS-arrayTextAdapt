function cpvilleinarray()
{
  $(".saisievillearray").each(function(){
    $(this).keypress(function(e) {
         var code = e.keyCode || e.which;
          if (code == 9) {
            e.preventDefault();
          }
    });
    
    var cache = {};
      $(this).autocomplete({
            serviceUrl : arrayTextAdapt.jsonurl,
            dataType: "json",
            paramName: 'term',
            minChars: 3,
            autoSelectFirst:true,
            transformResult: function(responses) {
                return {
                    suggestions: $.map(responses, function(ville) {
                        return { value: ville.label, data: ville };
                    })
                };
            },
            onSearchStart: function(query) {
                $( this ).prop("readonly",true);
            },
            onSearchComplete : function(query, suggestions) {
                $( this ).prop("readonly",false);
            },
            onSelect : function(suggestion) {
                if(suggestion.data) {
                    //~ $.each(suggestion.data, function(key, value) {
                        //~ $("input[type=text][name$='X"+qId+key+endLibel+"']").val(value).trigger('keyup');
                    //~ });
                }
                if(suggestion.data.value == "") {
                    $(this).val("");
                }
                $(this).trigger("keyup");
            }
      });
  });
}
