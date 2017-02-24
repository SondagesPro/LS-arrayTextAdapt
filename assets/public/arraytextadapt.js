/**
 * @file arrayTextAdapt public js
 * @author Denis Chenu
 * @copyright 2016 Comité Régional du Tourisme de Bretagne <http://www.tourismebretagne.com>
 * @copyright 2016 Advantages <https://advantages.fr/>
 * @copyright 2016 Denis Chenu <http://www.sondages.pro>
 * @license magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3-or-Later

 */


/**
 * Activate update text on checbox : @todo : fix it
 */
$(document).on("change",'.checkbox-arrayTextAdapt',function(){
  if($(this).is(":checked")){
    $("#"+$(this).data('update')).val($(this).attr('value'));
    checkconditions($(this).attr('value'), $(this).attr('name'), 'text');
    if($(this).data("setline"))
    {
      $(this).closest('.questions-list').find('input:checkbox').not($(this)).next("input:text").prop("disabled",false);
      $(this).closest('.questions-list').find('input:text').val($(this).data("setline")).trigger("keyup");
      var thiscell=$(this).closest('.answer-item');
      $(this).closest('.questions-list').find('.answer-item').not(thiscell).addClass("text-hidden arraytextadapt-hidden");
    }
  }else{
    $("#"+$(this).data('update')).val("");
    checkconditions("", $(this).attr('name'), 'text');
    if($(this).data("setline"))
    {
      $(this).closest('.questions-list').find('input:checkbox').not($(this)).next("input:text").prop("disabled",true);
      $(this).closest('.questions-list').find('input:text').val("").trigger("keyup");
      $(this).closest('.questions-list').find('.answer-item').removeClass("text-hidden arraytextadapt-hidden");
    }
  }
   $("#"+$(this).data('update')).keyup();

});

/**
 * Function to call for cpVille
 */
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
/**
 * Function to set star attribute to data-stars
 */
function setStarAttributes()
{
  $("[data-stars]").each(function()
  {
    var answersItem=$(this);
    var asNoAnswer=$(this).data('shownoanswer');
    var starsHtmlElement="<div class='radiostars-list noread' aria-hidden='true'>";
    var subquestion=$(this).closest("table.question");
    if(asNoAnswer){ starsHtmlElement= starsHtmlElement+"<div class='radiostar-rating radiostar-cancel fa fa-times' data-value=''></div>";}
    for (i=1; i<=$(this).data('stars'); i++) {
        var titlei=i;
        if($(subquestion).find(".star"+i).length)
        {
          titlei=$(subquestion).find(".star"+i).first().text().trim();
          $(subquestion).find(".star"+i).hide();
        }
        starsHtmlElement= starsHtmlElement+"<div class='radiostar-rating radiostar radiostar-"+i+" fa fa-star-o' data-value='"+i+"' title='"+titlei+"'></div>";
    };
    starsHtmlElement= starsHtmlElement+"</div>";
    $(this).after(starsHtmlElement);
    var starsElement=$(this).closest('.text-item').find('.radiostars-list');
    starsElement.on("mouseout mouseover", ".radiostar", function(event){
      var thisnum=$(this).index();
      if(asNoAnswer){thisnum--};
      if(event.type=='mouseover'){
        starsElement.children('.radiostar:lt('+thisnum+')').removeClass("fa-star-o").addClass("radiostar-drained fa-star");
        starsElement.children('.radiostar:eq('+thisnum+')').removeClass("fa-star-o").addClass("radiostar-drained radiostar-hover fa-star");
        starsElement.children('.radiostar:gt('+thisnum+')').removeClass("fa-star").addClass("fa-star-o");

      }else{
        starsElement.children('.radiostar:lt('+thisnum+')').removeClass("radiostar-drained");
        starsElement.children('.radiostar:eq('+thisnum+')').removeClass("radiostar-drained radiostar-hover");
        starsElement.children('.radiostar').addClass('fa-star-o');
        starsElement.children('.radiostar-rated').removeClass("fa-star-o").addClass('fa-star');

      }
    });
    starsElement.on("click", ".radiostar", function(event){
      var thisnum=$(this).index();
      var thischoice=$(this).data("value");
      answersItem.val(thischoice).keyup();
    });
    starsElement.on("click", ".radiostar-cancel", function(event){
      answersItem.val("").keyup();
    });
    answersItem.addClass("starred-text hide sr-only");
  });

}
/* Ensure keyup update star elements*/
$(document).on("keyup",".starred-text",function(){
  var openValue=$(this).val();
  var starsElement=$(this).next(".radiostars-list");

  if(openValue!="" && starsElement.find(".radiostar[data-value='"+openValue+"']").length){
    var checkedElement=starsElement.find(".radiostar[data-value='"+openValue+"']");
    var thisnum=starsElement.find(".radiostar").index(checkedElement);
    starsElement.children('.radiostar:lt('+thisnum+')').removeClass("fa-star-o radiostar-rated-on").addClass("radiostar-rated fa-star");
    starsElement.children('.radiostar:eq('+thisnum+')').removeClass("fa-star-o").addClass("radiostar-rated fa-star radiostar-rated-on");
    starsElement.children('.radiostar:gt('+thisnum+')').removeClass("radiostar-rated fa-star radiostar-rated-on").addClass("fa-star-o")
  }else{
    starsElement.children('.radiostar').removeClass("radiostar-rated  fa-star radiostar-rated-on").addClass("fa-star-o");
  }
});

$(document).ready(function(){
  $(".checkbox-arrayTextAdapt:checked").trigger("change");
  $(".starred-text").closest("table").find('input[type=text]:not(.hidden):not(.starred-text)[value=""]').val(" ").keyup();
  $(".starred-text").trigger("keyup");
  $(".starred-text").closest("table").find('input[type=text]:not(.hidden):not(.starred-text)[value=""]').on("blur focusout",function(){
    if ($(this).val() == ""){
      $(this).val(" ").keyup();
    }
  });
  /* fix label/checkbox place */
		$('.checkbox-arrayTextAdapt').closest("td").each(function(){
      $(this).closest("td").find('label').attr('for',$(this).find(".checkbox-arrayTextAdapt").attr('id')).appendTo($(this));
		});
});
