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
  
  //hide categories articles
  $(".links").hide();
  
  //remove article from screen
  hideOverlay(0);
  
  setup__interactivity();

  //articles related
	//$(".content-toggle").not(".code").hide(); // hide toggle content that is not code
  
  //if(article) open menu at this article
  openParentCategory(); // will open article

  //console.log(getFileDateFromUrl());

  display_article();
  
  $("#search").select();
});

function display_article(){
  var date = getFileDateFromUrl();
  if(date.length <= 0) return;

  var path = "pages/html/"+date+".html";
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
      $("#filter > .category-item").each(function(k,v){
      	var jq = $(v);
      	if(!jq.is(":hidden")){
      		console.log("---");
      		var a = jq.children(".cat-line-article");
      		console.log(a.attr("href"));
      		
      		window.location.replace(a.attr("href"));
      		//a.click();
      		//console.log(a);
      		return;
      	}
      });
    }
	});

}

function filterArticles(val, reset){
  //console.log(val, reset);

  if(reset){
    $("#categories").show();
    $("#filter").hide();
    return;
  }else{
    $("#filter").show();
    $("#categories").hide();
  }

  val = val.toLowerCase();

  $(".filter-link").each(function(){
    var t = $(this);
    var content = t.text().toLowerCase();

    if(content.indexOf(val) > -1){
      //console.log(content+","+val);
      t.show();
    }else{
      t.hide();
    }

    //console.log($(this).text());
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
  
  //click category
  $(".category").click(function(e){
    
    //inactive links
    if($(this).attr("href") != "#") return;
    
    e.preventDefault();

    //fetch articles of catego container
    var id = "links-"+$(this).attr("id");
    var elmt = $("#"+id);
    
    //masque tout les container de liens ouvert
    $(".links").not("[id='"+id+"']").hide();

    if(!elmt.is(":visible")){
      //reset lines behaviour (movement on move over)
      //elmt.children(".category-item").stop().css("margin-left", "0px");
      elmt.show(100);
    }else{
      elmt.hide();
    }

  });
  

  //hover on article or category make it go right a little
  $(".category, .category-item").hover(function(){
    $this = $(this);
    $this.stop().animate({marginLeft:"10px"}, 150);
  }, function(){
    $this = $(this);
    $this.stop().animate({marginLeft:'0px'}, 100);
  });

  //filter by subcategory
  $(".cat-line-sub").click(function(e){
    e.preventDefault();
    $this = $(this);
    var filter = $this.html();
    
    //hide all
    var cat = $this.parent().parent(); // line wrapper > links wrapper
    cat.children('.category-item').hide();

    //console.log("filtering "+filter+" for cat : "+cat.attr("id"));

    //show only what we want
    cat.children('.'+filter).show();
  });

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
  $("#overlay-bg").slideDown(500);
  
  $("#overlay").slideDown(700, function(){
    if(updateId < 0){
      //permet de resize le fond noir par rapport au resize de l'user
      updateId = setInterval(function(){ updateOverlay(); }, 200);
    }
  });

  $("#overlay-click").slideDown(100);
  //$("#overlay").show('slide', {direction: 'right'}, 200);
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
  var other = (delay <= 0) ? 0 : delay + 100; // 100 more ?
  $("#overlay-click").hide(other);
  $("#overlay-bg").hide(other);
  $("#overlay").hide(delay);
  
  //remove article selection
  $(".category-item").removeClass("category-item-selected");

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