//website palette colors
var colors = [
  //["fff", "000", "555", "AAA", "555", "777", "111"]
  ["fafafa", "121212", "5a5a5a", "a1a1a1", "555", "777", "111"]
  //["040a19", "1b543d", "7d8c35", "c7a734", "fab95b", "de7f50"]
  //["0f141f", "46594B", "A6977C", "D9B384", "734F30", "532e25"]
];

$(function(){

  setup_search();

  //set website color (random)
  setPalette();

  //remove article from screen
  hideOverlay(0);

  setup__interactivity();

  display_article();

  $("#search").select();
});

function display_article(){
  var date = getFileDateFromUrl();
  if(date.length <= 0) return;

  var path = "articles/"+date+".html";
  //console.log(path);

  $.get(path,function(data){
    $("#overlay").html(data);
    openOverlay();
  });
  
}

function setup_search(){
  var defaultVal = $("#search").val();
  var input = $("#search");

  //console.log("default ? "+defaultVal);

  $("#search").focus(function(){
    //console.log("focus");
    if(input.val() == defaultVal) input.val("");
  }).blur(function(){
    //console.log("blur");
    if(input.val().length == 0) input.val(defaultVal);
  });

  $("#search").bind("change paste keyup", function(){
    filterArticles($(this).val(), input.val().length == 0 || input.val() == defaultVal);
  });

	$('input').bind('keydown', function(e) {
    if (e.which == 13 || e.keyCode == 13) {
      e.preventDefault();
      var first = $(".article-line").filter(":visible").first();
      if(first.length) window.location.replace(first.attr("href"));
    }
  });

}

function filterArticles(val, reset){
  clearSubcatFilter();

  if(reset){
    $(".article-line").show();
    return;
  }

  val = val.toLowerCase();

  $(".article-line").each(function(){
    var t = $(this);
    var content = t.text().toLowerCase();

    if(content.indexOf(val) > -1) t.show();
    else t.hide();
  });
}

function getFileDateFromUrl(){
  var url = document.URL;
  if(url[url.length] == "/") url = url.substring(0,url.length-2);
  var data = url.split("/");
  return data[data.length-1];
}

function setup__interactivity(){

  //remove article overlay
  $("#overlay-click").click(function(e){
    hideOverlay(100);
  });
  
  //click a subcat badge : hide every article line with a different subcat, click again to reset
  $(".subcat").click(function(e){
    e.preventDefault();
    e.stopPropagation();

    var $this = $(this);
    var subcat = $this.attr("data-subcat");

    if($this.hasClass("subcat-active")){
      clearSubcatFilter();
      return;
    }

    clearSubcatFilter();
    $this.addClass("subcat-active");

    $(".article-line").each(function(){
      var line = $(this);
      if(line.find(".subcat").attr("data-subcat") !== subcat){
        line.addClass("subcat-hidden");
      }
    });
  });

}

function clearSubcatFilter(){
  $(".subcat").removeClass("subcat-active");
  $(".article-line").removeClass("subcat-hidden");
}

function trim (myString)
{
  return myString.replace(/^\s+/g,'').replace(/\s+$/g,'')
}
 
function openParentCategory(){
  var date = $("#overlay").attr("rel");
  date = trim(date);
  if(date.length <= 0)  return;
  
  //ouvrir le menu de l'article selectionné
  var articles = $(".cat-line-article"); // gather all article LINKS
  var current = null;
  for(i = 0; i < articles.length; i++){
    var jQueryObject = $(articles[i]);
    //console.log(jQueryObject.attr("href")+" == "+date);
    if(jQueryObject.attr("href") == date){
      current = jQueryObject;
    }
  }
  
  //si c'est un article, on ouvre son parent
  if(current != null){
    current.parent().parent().show(); //category > line wrapper > link wrapper
    current.addClass("category-item-selected");
  }
  
  //charger l'article de l'url
  updateOverlay(); // resize to fit article

  openOverlay();
  Shadowbox.init();
}

var updateId = -1;
function openOverlay(){
  updateOverlay();

  $("#overlay-bg").css("display", "block").addClass("fade-in");

  $("#overlay").css("display", "block").removeClass("article-in");
  void $("#overlay")[0].offsetWidth; // force reflow so the animation replays
  $("#overlay").addClass("article-in");

  if(updateId < 0){
    //permet de resize le fond noir par rapport au resize de l'user
    updateId = setInterval(function(){ updateOverlay(); }, 200);
  }

  $("#overlay-click").css("display", "block");
}

/* setup black overlay based on the size of the screen */
function updateOverlay(){
  var clickLayer = $("#overlay-click"); // zone transparente pour fermer l'article
  var bg = $("#overlay-bg"); // zone sombre derrière
  var overlay = $("#overlay"); // zone de texte
  
  //background layer to receive click to remove overlay
  var winWidth = parseInt($(window).width());
  //var winHeight = parseInt($(window).height());
  var winHeight = parseInt($(document).height());
  
  if(getInternetExplorerVersion() >= 0){
    winWidth = screen.width;
    winHeight = screen.height;
  }
  
  //alert(winWidth+", "+winHeight);
  clickLayer.css("width", winWidth);
  
  var h = Math.max(parseInt(overlay.css("height")) + 75, winHeight);
  clickLayer.css("height",h);
  
  //setup black background behind text
  bg.css("width",parseInt(overlay.css("width")) + 50);
  bg.css("height",h);
  //bg.css("left", Math.max(0, (($(window).width() - $(this).outerWidth()) * 0.5) + $(window).scrollLeft()) + "px");
  
  //setup text zone
  //overlay.css("left", Math.max(0, (($(window).width() - $(this).outerWidth()) * 0.5) + $(window).scrollLeft()) + "px");
}

function hideOverlay(delay){
  $("#overlay-click").hide(delay);
  $("#overlay-bg").hide(delay).removeClass("fade-in");
  $("#overlay").hide(delay).removeClass("article-in");

  //vire le process qui permet de resize le fond noir si l'user change la taille de son navigateur
  if(updateId > -1){
    //console.log("cleared");
    clearInterval(updateId);
    updateId = -1;
  }
}

function setPalette(){
  var rand = Math.floor(Math.random() * colors.length);
  var palette = colors[rand];
  //console.log(rand, colors, palette);
  $(document.body).css("background-color","#"+palette[0]);
  $(".category").each(function(index){
    var paletteId = $(this).attr("rel");
    var color = "#"+palette[paletteId];
    $(this).css("color",color);
    
    //set same color to all link children
    var id = "."+$(this).attr("id");
    $(id).css("color",color);
  });
}




function getInternetExplorerVersion()
// Returns the version of Internet Explorer or a -1
// (indicating the use of another browser).
{
  var rv = -1; // Return value assumes failure.
  if (navigator.appName == 'Microsoft Internet Explorer')
  {
    var ua = navigator.userAgent;
    var re  = new RegExp("MSIE ([0-9]{1,}[\.0-9]{0,})");
    if (re.exec(ua) != null)
      rv = parseFloat( RegExp.$1 );
  }
  return rv;
}
function checkVersion()
{
  var msg = "You're not using Internet Explorer.";
  var ver = getInternetExplorerVersion();

  if ( ver > -1 )
  {
    if ( ver >= 8.0 ) 
      msg = "You're using a recent copy of Internet Explorer."
    else
      msg = "You should upgrade your copy of Internet Explorer.";
  }
  alert( msg );
}