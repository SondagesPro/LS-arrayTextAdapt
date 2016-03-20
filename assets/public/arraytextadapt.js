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
      minLength: 1,
      position: { my : "left top", at: "left bottom", collision: "flipfit" },
      source: function(request, response) {
          $.ajax({
              url: arrayTextAdapt.jsonurl,
              dataType: "json",
              data: {
                  term : request.term
              },
              success: function(data) {
                response( $.map( data, function( item ) {
                    item.value="["+item.cp+"] "+item.nom;
                    return item;
                }));
              }
          });
      },
      change: function (event, ui) {
        if(!ui.item){
            $(this).val("");
        }
      },
      select: function( event, ui ) {
          console.log(ui);
          //$(this).trigger('keyup').trigger('blur');
      },
      focus: function (event, ui) {
        return false;
      },
      blur: function (event, ui) {
        $(this).trigger("change");
        return false;
      }
    });
  });
}
