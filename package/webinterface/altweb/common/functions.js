// This is free software, licensed under the GNU General Public License
// version 3 as published by the Free Software Foundation; you can
// redistribute it and/or modify it under the terms of the GNU
// General Public License; and comes with ABSOLUTELY NO WARRANTY.

// functions.js for AstLinux
//   June 2009 - David Kerr
//   August 2017 - Updated



// The JX object is a modified version of V3.01.A obtained from the OpenJS website. The original
// source script contained no copyright notice, but comments on the OpenJS web site indicate that it
// is licensed under terms of the BSD license and therefore free to use and redistribute
//
// jx is a simple object that lets us build AJAX like web experience.
//
//V3.01.A - http://www.openjs.com/scripts/jx/
jx = {
  //Create a xmlHttpRequest object - this is the constructor.
  getHTTPObject : function() {
    var http = false;
    //Use the XMLHttpRequest of Firefox/Mozilla etc. to load the document.
    if (window.XMLHttpRequest) {
      try {http = new XMLHttpRequest();}
      catch (e) {http = false;}
    } else
    //Use IE's ActiveX items to load the file.
    if (typeof ActiveXObject != 'undefined') {
      try {http = new ActiveXObject("Msxml2.XMLHTTP");}
      catch (e) {
        try {http = new ActiveXObject("Microsoft.XMLHTTP");}
        catch (E) {http = false;}
      }
    }
    return http;
  }, // end of getHTTPObject()

  // This function is called from the user's script.
  //Arguments -
  //  url - The url of the serverside script that is to be called. Append all the arguments to
  //      this url - eg. 'get_data.php?id=5&car=benz'
  //  callback - Function that must be called once the data is ready.
  //  format - The return type for this function. Could be 'xml','json' or 'text'. If it is json,
  //      the string will be 'eval'ed before returning it. Default:'text'
  //  method - GET or POST. Default 'GET'
  load : function (url, callback, format, method, opt) {
    var http = this.init(); //The XMLHttpRequest object is recreated at every call - to defeat Cache problem in IE
    if(!http||!url) return;

    //XML Format need this for some Mozilla Browsers
    if (http.overrideMimeType) http.overrideMimeType('text/xml');
    if(!method) method = "GET";//Default method is GET
    if(!format) format = "text";//Default return type is 'text'
    if(!opt) opt = {};
    format = format.toLowerCase();
    method = method.toUpperCase();

    //Kill the Cache problem in IE.
    // DAK - Commented out
    //var now = "uid=" + new Date().getTime();
    //url += (url.indexOf("?")+1) ? "&" : "?";
    //url += now;

    var parameters = null;
    var nParams = 0;

    if(method=="POST") {
      var parts = url.split("\?");
      url = parts[0];
      if ((nParams = parts.length-1) > 0) parameters = parts[1];
    }
    http.open(method, url, true);

    if(method=="POST") {
      http.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
      http.setRequestHeader("Content-length", nParams);
      http.setRequestHeader("Connection", "close");
    }

    var ths = this; // Closure

    if(opt.handler) { //If a custom handler is defined, use it
      http.onreadystatechange = function() { opt.handler(http); };
    } else {
      http.onreadystatechange = function () {//Call a function when the state changes.
        if (http.readyState == 4) {//Ready State will be 4 when the document is loaded.
          if(http.status == 200) {
            var result = "";
            if(http.responseText) result = http.responseText;
            //If the return is in JSON format, eval the result before returning it.
            if(format.charAt(0) == "j") {
              //\n's in JSON string, when evaluated will create errors in IE
              result = result.replace(/[\n\r]/g,"");
              result = eval('('+result+')');

            } else if(format.charAt(0) == "x") { //XML Return
              result = http.responseXML;
            }

            //Give the data to the callback function.
            if(callback) callback(result);
          }
          else {
            if(opt.loadingIndicator) document.getElementsByTagName("body")[0].removeChild(opt.loadingIndicator); //Remove the loading indicator
            if(opt.loading) document.getElementById(opt.loading).style.display="none"; //Hide the given loading indicator.

            if(error) error(http.status);
          }
        }
      }
    }
    http.send(parameters);
  }, // end of load()

  bind : function(user_options) {
    var opt = {
      'url':'',       //URL to be loaded
      'onSuccess':false,  //Function that should be called at success
      'onError':false,  //Function that should be called at error
      'format':"text",  //Return type - could be 'xml','json' or 'text'
      'method':"GET",   //GET or POST
      'update':"",    //The id of the element where the resulting data should be shown.
      'loading':"",   //The id of the loading indicator. This will be set to display:block when the url is loading and to display:none when the data has finished loading.
      'loadingIndicator':"" //HTML that would be inserted into the document once the url starts loading and removed when the data has finished loading. This will be inserted into a div with class name 'loading-indicator' and will be placed at 'top:0px;left:0px;'
    }
    for(var key in opt) {
      if(user_options[key]) {//If the user given options contain any valid option, ...
        opt[key] = user_options[key];// ..that option will be put in the opt array.
      }
    }

    if(!opt.url) return; //Return if a url is not provided

    var div = false;
    if(opt.loadingIndicator) { //Show a loading indicator from the given HTML
      div = document.createElement("div");
      div.setAttribute("style","position:absolute;top:0px;left:0px;");
      div.setAttribute("class","loading-indicator");
      div.innerHTML = opt.loadingIndicator;
      document.getElementsByTagName("body")[0].appendChild(div);
      this.opt.loadingIndicator=div;
    }
    if(opt.loading) document.getElementById(opt.loading).style.display="block"; //Show the given loading indicator.

    this.load(opt.url,function(data) {
      if(opt.onSuccess) opt.onSuccess(data);
      if(opt.update) document.getElementById(opt.update).innerHTML = data;
      if(div) document.getElementsByTagName("body")[0].removeChild(div); //Remove the loading indicator
      if(opt.loading) document.getElementById(opt.loading).style.display="none"; //Hide the given loading indicator.
    },opt.format,opt.method, opt);

  }, // end of bind()

  init : function() {return this.getHTTPObject();}
}


// addEventSimple, removeEventSimple, findPos and dragDrop object are derived from scripts at Quirksmode.org.
// The original source examples contain no copyright notice or license. The Quirksmode web site states
// that all examples, including these, may be copied, modified, redistributed, etc. freely.
function addEventSimple(obj,evt,fn) {
  if (obj.addEventListener)
    obj.addEventListener(evt,fn,false);
  else if (obj.attachEvent)
    obj.attachEvent('on'+evt,fn);
}

function removeEventSimple(obj,evt,fn) {
  if (obj.removeEventListener)
    obj.removeEventListener(evt,fn,false);
  else if (obj.detachEvent)
    obj.detachEvent('on'+evt,fn);
}

function findPos(obj) {
  var curleft = 0;
  var curtop = 0;
  if (obj.offsetParent) {
    do {
      curleft += obj.offsetLeft;
      curtop += obj.offsetTop;
    } while (obj = obj.offsetParent);
  }
  return [curleft,curtop];
}

dragDrop = {
  initialMouseX: undefined,
  initialMouseY: undefined,
  startX: undefined,
  startY: undefined,
  draggedObject: undefined,
  dragElem: undefined,
  initElement: function (element,dragObj) {
    if (typeof element == 'string') element = document.getElementById(element);
    if (typeof dragObj == 'string') dragObj = document.getElementById(dragObj);
    if (dragObj) dragDrop.dragElem = dragObj;
    element.onmousedown = dragDrop.startDragMouse;
  },
  startDragMouse: function (e) {
    if (dragDrop.dragElem) dragDrop.startDrag(dragDrop.dragElem);
    else dragDrop.startDrag(this);
    var evt = e || window.event;
    dragDrop.initialMouseX = evt.clientX;
    dragDrop.initialMouseY = evt.clientY;
    addEventSimple(document,'mousemove',dragDrop.dragMouse);
    addEventSimple(document,'mouseup',dragDrop.releaseElement);
    return false;
  },
  startDrag: function (obj) {
    if (dragDrop.draggedObject) dragDrop.releaseElement();
    dragDrop.startX = obj.offsetLeft;
    dragDrop.startY = obj.offsetTop;
    dragDrop.draggedObject = obj;
    obj.className += ' dragged';
  },
  dragMouse: function (e) {
    var evt = e || window.event;
    var dX = evt.clientX - dragDrop.initialMouseX;
    var dY = evt.clientY - dragDrop.initialMouseY;
    dragDrop.draggedObject.style.left = dragDrop.startX + dX + 'px';
    dragDrop.draggedObject.style.top = dragDrop.startY + dY + 'px';
    return false;
  },
  releaseElement: function() {
    removeEventSimple(document,'mousemove',dragDrop.dragMouse);
    removeEventSimple(document,'mouseup',dragDrop.releaseElement);
    dragDrop.draggedObject.className = dragDrop.draggedObject.className.replace(/dragged/,'');
    dragDrop.draggedObject = null;
  }
}

// Following globals are to manage the popup window into which we can display anything we want.
var popupOpenTimer = 0;
var popupCloseTimer = 0;
var popupCache = new Object;
var popupOnHover = false;
var popupMouseHere = false;
var triggerElement = null;

// delayPopup registered to any element which we want to display a popup either immediately (on click)
// or after a timeout delay (mouse hovering over an element for some specified time)
// e = event, url = string url,
// w = width, h = height,
// title = string title for the popup
// fixed = boolean true (popup will not scroll with document) or false (will scroll with document)
// delay = 0 to display imediately or time in miliseconds after which to display the popup (unless cancelled)
function delayPopup(e, url, w, h, title, fixed, delay) {
  // if delay is negative, then don't do anything.
  if (delay < 0) return;

  // First find the element we clicked on.
  var targ;
  if (!e) var e = window.event;
  if (e.currentTarget) targ = e.currentTarget;
  else if (e.target) targ = e.target;
  else if (e.srcElement) targ = e.srcElement;
  if (targ.nodeType == 3) targ = targ.parentNode;
  triggerElement = targ;

  // Now call the loadPopup() function. Either immediately or after a specified delay (in milliseconds)
  // first cancel any existing timer
  if (popupOpenTimer) {
    clearTimeout(popupOpenTimer);
    popupOpenTimer = 0;
  }

  // now load the popup, either immediately or after specified delay
  if (delay == 0) loadPopup();
  else popupOpenTimer = setTimeout(loadPopup,delay);

  // The rest we do inside the loadPopup() function
  function loadPopup() {
    // clear timer that (may) have triggered this
    popupOpenTimer = 0;

    // Now find our popup <div> and set its width and height
    var popup = document.getElementById('popup_div');
    if (!popup) {
      // Could not find the element. Therefore need to create it.
      popup = document.createElement('div');
      popup.id = 'popup_div';
      popup.className = 'popup';
      popup.onmouseover = function(event){popupMouseOver();}
      popup.onmouseout = function(event){popupMouseOut(event,this);}
      popup.style.display = 'none';
      var popupTable = document.createElement('table');
      popupTable.id = 'popup_title';
      popupTable.className = 'popup_title'

      var popupTr = document.createElement('tr');
      popupTr.className = 'popup_title_tr';

      var popupTd1 = document.createElement('td');
      popupTd1.className = 'popup_title_loading';

      var popupTd1Div = document.createElement('div');
      popupTd1Div.id = 'popup_loading';
      popupTd1Div.style.display = 'none';
      popupTd1Div.style.cursor = 'move';
      popupTd1Div.innerHTML = '<img src="../common/ajax-loader1.gif" alt="Loading..." />';
      popupTd1.appendChild(popupTd1Div);
      popupTr.appendChild(popupTd1);

      var popupTd2 = document.createElement('td');
      popupTd2.id = 'popup_title_text';
      popupTd2.className = 'popup_title_text';
      popupTr.appendChild(popupTd2);

      var popupTd3 = document.createElement('td');
      popupTd3.id = 'popup_external_link';
      popupTd3.className = 'popup_title_X';
      popupTr.appendChild(popupTd3);

      var popupTd4 = document.createElement('td');
      popupTd4.className = 'popup_title_X';
      popupTd4.innerHTML =' X';
      popupTd4.onclick = function(event){hidePopup();}
      popupTr.appendChild(popupTd4);
      popupTable.appendChild(popupTr);
      popup.appendChild(popupTable);

      var popupText = document.createElement('div');
      popupText.id = 'popup_text';
      popupText.className = 'popup_text';
      popup.appendChild(popupText);

      document.body.appendChild(popup);
    }

    document.getElementById('popup_title_text').innerHTML = title;
    var popupText = document.getElementById('popup_text');
    popupText.innerHTML = 'Loading...';

    // First find by how much the page is scrolled. We will use this to adjust the position of the
    // popup so that the title bar is not above the top of the visible area.
    var sXY = getScrollXY();

    // Find size of window. We use this to adjust the position of the popup so that the right side
    // is still visible (and therefore the X to close is visible)
    // and also to make sure that the width is no wider than the brower window (but at least 100 pixels)
    var wXY = windowSize();

    popupText.style.width = Math.max(Math.min(w,wXY[0]-40),100)+'px';
    popupText.style.height = h+'px';

    // Now find out whether the position style of the popup is absolute (will scroll with page) or fixed (will not scroll)
    if (fixed != null) {
      if (fixed == true) popup.style.position = 'fixed';
      else popup.style.position = 'absolute';
    }
    else {
      // undefined, so find out from existing style
      fixed = false;
      var pv = null;
      if (popup.currentStyle) pv = popup.currentStyle['position'];
      else if (window.getComputedStyle) {
        var compStyle = window.getComputedStyle(popup, "");
        pv = compStyle.getPropertyValue('position');
      }
      if (pv == "fixed") fixed = true;
    }

    // Now find position of the element we clicked on.
    var pos = findPos(targ);

    // Set the position of the popup on the screen
    popup.style.left = (Math.min(wXY[0] - w - 20, ((pos[0]+targ.offsetWidth/2)) - ((fixed==true)?sXY[0]:0)))+'px';
    popup.style.top =  (Math.max(sXY[1], (pos[1]- h - 22)) - ((fixed==true)?sXY[1]:0))+'px';

    // Enable moving of the popup
    dragDrop.initElement('popup_title',popup);

    // Display the popup, remembering whether this is immediate or result of delay timeout.
    if (delay !=0) popupOnHover = true;
    else popupOnHover = false;

    var popupLink = document.getElementById('popup_external_link');
    popupLink.innerHTML ='<a href="'+url+'" target="_blank"><img src="../common/open-new-window.png" height="20" width="20" alt="->"/></a>';
    popup.style.display = '';

    // If this was a delayed (onmouseover) request, then look see if it was in the cache first.
    // This is to improve performance when user is hovering over an element that triggered this
    // popup. If user clicks, then there will be no delay, and we'll use that as a sign that
    // the content should be fetched again from the server.
    if ((delay != 0) && (popupCache[url] != null)) {
      popupText.innerHTML = popupCache[url];
    }
    else {
      // Send request to load content. This will happen asynchronously.
      jx.bind( { "url" : url,
             "method" : "GET",
             "onSuccess" : addToCache,
             "loading" : "popup_loading" } );
    }

    // addToCache saves the result of all requests for popup text from the server.
    // Thus improving performance for popups generated by user hovering over an element.
    // Cache will be cleared by the browser on page reload.
    // We also use this to fixup embedded links in the html if necessary.
    function addToCache(result) {
      var i;
      popupText.innerHTML = result;
      var anchors = popupText.getElementsByTagName("a");
      // Make all links open in new browser tab/window as we are in a "popup"
      for (i=0; i < anchors.length; i++) anchors[i].target = "_blank";

      // If the URL we are loading into this <div> comes from a different web
      // server then we will need to fixup any relative URLs that will have
      // been set relative to the base URL of the containing browser window.
      var baseurl = window.location.href.split('/').slice(0, 3).join('/');
      var targetbaseurl = url.split('/').slice(0, 3).join('/');
      if (baseurl != targetbaseurl) {
        // We only do this for anchor href's and image src's as for our use
        // case that is all we need.
        for (i=0; i < anchors.length; i++) {
          if (anchors[i].href.includes("#")) anchors[i].href = url + "#" + anchors[i].href.split('#')[1];
          else anchors[i].href = anchors[i].href.replace(baseurl, targetbaseurl);
        }
        var images = popupText.getElementsByTagName("img");
        for (i=0; i < images.length; i++) {
          images[i].src = images[i].src.replace(baseurl, targetbaseurl);
        }
      }
      popupCache[url] = popupText.innerHTML;
    } // end of addToCache()
  } // end of loadPopup()
} // end of delayPopup()

// delayPopupCancel is registered to onmouseout on any element that has a popup that can be triggered by hovering over it.
// Function is to cause the popup not to display (if timeout has not expired yet) or to hide the popup (if it is already visible)
function delayPopupCancel() {
  // If a timer is active then cancel it so that popup will not display on timeout.
  if (popupOpenTimer) {
    clearTimeout(popupOpenTimer);
    popupOpenTimer = 0;
  }

  // if popup is already visible because of user mouse hovering over the element longer than the timeout delay
  // then we will hide the popup.  Note that we put in a 100ms delay here. This is because the user may have
  // moved the mouse over the top of the popup, in which case we don't want to hide it. The 100ms delay gives
  // time for events (mouseover, mouseout) to propogate before we try and hide it.
  if (popupOnHover) popupCloseTimer = setTimeout(delayHidePopup,100);
  else popupCloseTimer = 0;

  // delayHidePopup called after 100ms.
  function delayHidePopup() {
    // If the mouse is NOT hovering over the popup DIV then hide the popup.
    if (!popupMouseHere) {
      var popup = document.getElementById('popup_div');
      popup.style.display = 'none';
      popupCloseTimer = 0;
      triggerElement = null;
    }
  } // end of delayHidePopup()
} // end of delayPopupCancel()

// hidePopup is registered to the close X in the popup DIV title bar. Called when user clicks on the X.
// also called elsewhere in this script to hide the popup.
function hidePopup() {
  var popup = document.getElementById('popup_div');
  popup.style.display = 'none';
  triggerElement = null;
}

// popupMouseOver is registered to the popup DIV and triggers whenever mouse moves onto the popup
// we use this to keep track of if the mouse is over the popup or not.
function popupMouseOver() {
  popupMouseHere = true;
}

// popupMouseOut is registered to the popup DIV and triggers whenever mouse moves out of the popup.
// we use this to decide if to hide the popup.
function popupMouseOut(e, t) {
  if (!e) var e = window.event;
  // Find related target (where we are moving out to)
  var reltg = (e.relatedTarget) ? e.relatedTarget : ((e.toElement) ? e.toElement : e.currentTarget);

  // If we moved back onto the element that triggered the popup then don't hide it.
  if (reltg != triggerElement) {
    // Find out if this is a child of the popup DIV by searching up the parent tree until we
    // hit BODY (not a child) or we hit an element with the same id as the popup DIV
    while (reltg && (reltg.tagName != 'BODY')) {
      if (reltg.id == t.id) return;
      reltg = reltg.parentNode;
      if ((reltg == null) || (reltg == undefined)) return;
    }
    // We are really moving out of the popup (mouse now over another element on the page)
    // IF the popup is displayed because of a hover (as opposed to a click) then hide it.
    // If popup is displayed because of a click, do not hide... user must explicitly click on the close X
    if (popupMouseHere && popupOnHover) hidePopup();
  }
  popupMouseHere = false;
}

function loadHtml(url, div, loadindicator, method) {
  // append the tag 'ajax' to tell the host to only send the updated html, not the whole page
  if (!method) method = 'POST';
  url += (url.indexOf("?")+1) ? "&" : "?";
  url += 'ajax=true';
  jx.bind( { "url" : url,
             "method" : method,
             "update" : div,
             "loading" : loadindicator } );
}

var clickedElement = null;

function submitForm(e, url, div, loadindicator, method) {
  // First find the element we clicked on.
  if (!e) var e = window.event;

  if ((e.type == 'submit') && (clickedElement != null)){
    var targ;
    if (e.currentTarget) targ = e.currentTarget;
    else if (e.target) targ = e.target;
    else if (e.srcElement) targ = e.srcElement;
    if (targ.nodeType == 3) targ = targ.parentNode;

    url += (url.indexOf("?")+1) ? "&" : "?";
    url += clickedElement.name+'='+clickedElement.value;
    var elem = null;
    var i = 0;
    for (i = 0; i < targ.elements.length; i++) {
      elem = targ.elements[i];
      if ( ((elem.type == 'hidden') && (elem.value != ''))
        || ((elem.type == 'text') && (elem.value != ''))
        || ((elem.type == 'select-one') && (elem.value != ''))
        || ((elem.type == 'checkbox') && (elem.checked == true)) ) {
        url += '&'+elem.name+'='+elem.value;
      }
    }
  }
  loadHtml(url, div, loadindicator, method);
}

function getScrollXY() {
  var scrOfX = 0, scrOfY = 0;
  if( typeof( window.pageYOffset ) == 'number' ) {
    //Netscape compliant
    scrOfY = window.pageYOffset;
    scrOfX = window.pageXOffset;
  } else if( document.body && ( document.body.scrollLeft || document.body.scrollTop ) ) {
    //DOM compliant
    scrOfY = document.body.scrollTop;
    scrOfX = document.body.scrollLeft;
  } else if( document.documentElement && ( document.documentElement.scrollLeft || document.documentElement.scrollTop ) ) {
    //IE6 standards compliant mode
    scrOfY = document.documentElement.scrollTop;
    scrOfX = document.documentElement.scrollLeft;
  }
  return [ scrOfX, scrOfY ];
}

function windowSize() {
  var myWidth = 0, myHeight = 0;
  if( typeof( window.innerWidth ) == 'number' ) {
    //Non-IE
    myWidth = window.innerWidth;
    myHeight = window.innerHeight;
  } else if( document.documentElement && ( document.documentElement.clientWidth || document.documentElement.clientHeight ) ) {
    //IE 6+ in 'standards compliant mode'
    myWidth = document.documentElement.clientWidth;
    myHeight = document.documentElement.clientHeight;
  } else if( document.body && ( document.body.clientWidth || document.body.clientHeight ) ) {
    //IE 4 compatible
    myWidth = document.body.clientWidth;
    myHeight = document.body.clientHeight;
  }
  return [ myWidth, myHeight];
}
