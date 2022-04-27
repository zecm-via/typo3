/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
var __importDefault=this&&this.__importDefault||function(t){return t&&t.__esModule?t:{default:t}};define(["require","exports","jquery","TYPO3/CMS/Core/Ajax/AjaxRequest","./ContextMenuActions","TYPO3/CMS/Core/Event/DebounceEvent","TYPO3/CMS/Core/Event/RegularEvent","TYPO3/CMS/Core/Event/ThrottleEvent"],(function(t,e,s,n,i,o,a,l){"use strict";s=__importDefault(s);class u{constructor(){this.mousePos={X:null,Y:null},this.record={uid:null,table:null},this.eventSources=[],this.storeMousePositionEvent=t=>{this.mousePos={X:t.pageX,Y:t.pageY}},s.default(document).on("click contextmenu",".t3js-contextmenutrigger",t=>{const e=s.default(t.currentTarget);e.prop("onclick")&&"click"===t.type||(t.preventDefault(),this.show(e.data("table"),e.data("uid"),e.data("context"),e.data("iteminfo"),e.data("parameters"),t.target))}),new l("mousemove",this.storeMousePositionEvent.bind(this),50).bindTo(document)}static drawActionItem(t){const e=t.additionalAttributes||{};let s="";for(const t of Object.entries(e)){const[e,n]=t;s+=" "+e+'="'+n+'"'}return'<li role="menuitem" class="list-group-item" tabindex="-1" data-callback-action="'+t.callbackAction+'"'+s+'><span class="list-group-item-icon">'+t.icon+"</span> "+t.label+"</li>"}static within(t,e,s){const n=t.getBoundingClientRect(),i=window.pageXOffset||document.documentElement.scrollLeft,o=window.pageYOffset||document.documentElement.scrollTop,a=e>=n.left+i&&e<=n.left+i+n.width,l=s>=n.top+o&&s<=n.top+o+n.height;return a&&l}show(t,e,s,n,i,o=null){this.hideAll(),this.record={table:t,uid:e};const a=o.matches("a, button, [tabindex]")?o:o.closest("a, button, [tabindex]");this.eventSources.push(a);let l="";void 0!==t&&(l+="table="+encodeURIComponent(t)),void 0!==e&&(l+=(l.length>0?"&":"")+"uid="+e),void 0!==s&&(l+=(l.length>0?"&":"")+"context="+s),void 0!==n&&(l+=(l.length>0?"&":"")+"enDisItems="+n),void 0!==i&&(l+=(l.length>0?"&":"")+"addParams="+i),this.fetch(l)}initializeContextMenuContainer(){if(0===s.default("#contentMenu0").length){const t='<div id="contentMenu0" class="context-menu" style="display: none;"></div><div id="contentMenu1" class="context-menu" data-parent="#contentMenu0" style="display: none;"></div>';s.default("body").append(t),document.querySelectorAll(".context-menu").forEach(t=>{new a("mouseenter",t=>{t.target;this.storeMousePositionEvent(t)}).bindTo(t),new o("mouseleave",t=>{const e=t.target,s=document.querySelector('[data-parent="#'+e.id+'"]');if(!u.within(e,this.mousePos.X,this.mousePos.Y)&&(null===s||null===s.offsetParent)){let t;this.hide("#"+e.id),void 0!==e.dataset.parent&&null!==(t=document.querySelector(e.dataset.parent))&&(u.within(t,this.mousePos.X,this.mousePos.Y)||this.hide(e.dataset.parent))}},500).bindTo(t)})}}fetch(t){const e=TYPO3.settings.ajaxUrls.contextmenu;new n(e).withQueryArguments(t).get().then(async t=>{const e=await t.resolve();void 0!==t&&Object.keys(t).length>0&&this.populateData(e,0)})}populateData(e,n){this.initializeContextMenuContainer();const o=s.default("#contentMenu"+n);if(o.length&&(0===n||s.default("#contentMenu"+(n-1)).is(":visible"))){const a=this.drawMenu(e,n);o.html('<ul class="list-group" role="menu">'+a+"</ul>"),s.default("li.list-group-item",o).on("click",e=>{e.preventDefault();const o=s.default(e.currentTarget);if(o.hasClass("list-group-item-submenu"))return void this.openSubmenu(n,o,!1);const a=o.data("callback-action"),l=o.data("callback-module");o.data("callback-module")?t([l],t=>{t[a].bind(o)(this.record.table,this.record.uid)}):i&&"function"==typeof i[a]?i[a].bind(o)(this.record.table,this.record.uid):console.log("action: "+a+" not found"),this.hideAll()}),s.default("li.list-group-item",o).on("keydown",t=>{const e=s.default(t.currentTarget);switch(t.key){case"Down":case"ArrowDown":this.setFocusToNextItem(e.get(0));break;case"Up":case"ArrowUp":this.setFocusToPreviousItem(e.get(0));break;case"Right":case"ArrowRight":if(!e.hasClass("list-group-item-submenu"))return;this.openSubmenu(n,e,!0);break;case"Home":this.setFocusToFirstItem(e.get(0));break;case"End":this.setFocusToLastItem(e.get(0));break;case"Enter":case"Space":e.click();break;case"Esc":case"Escape":case"Left":case"ArrowLeft":this.hide("#"+e.parents(".context-menu").first().attr("id"));break;case"Tab":this.hideAll();break;default:return}t.preventDefault()}),o.css(this.getPosition(o,!1)).show(),s.default("li.list-group-item[tabindex=-1]",o).first().focus()}}setFocusToPreviousItem(t){let e=this.getItemBackward(t.previousElementSibling);e||(e=this.getLastItem(t)),e.focus()}setFocusToNextItem(t){let e=this.getItemForward(t.nextElementSibling);e||(e=this.getFirstItem(t)),e.focus()}setFocusToFirstItem(t){let e=this.getFirstItem(t);e&&e.focus()}setFocusToLastItem(t){let e=this.getLastItem(t);e&&e.focus()}getItemBackward(t){for(;t&&(!t.classList.contains("list-group-item")||"-1"!==t.getAttribute("tabindex"));)t=t.previousElementSibling;return t}getItemForward(t){for(;t&&(!t.classList.contains("list-group-item")||"-1"!==t.getAttribute("tabindex"));)t=t.nextElementSibling;return t}getFirstItem(t){return this.getItemForward(t.parentElement.firstElementChild)}getLastItem(t){return this.getItemBackward(t.parentElement.lastElementChild)}openSubmenu(t,e,n){this.eventSources.push(e[0]);const i=s.default("#contentMenu"+(t+1)).html("");e.next().find(".list-group").clone(!0).appendTo(i),i.css(this.getPosition(i,n)).show(),s.default(".list-group-item[tabindex=-1]",i).first().focus()}getPosition(t,e){let n=0,i=0;if(this.eventSources.length&&(null===this.mousePos.X||e)){const t=this.eventSources[this.eventSources.length-1].getBoundingClientRect();n=this.eventSources.length>1?t.right:t.x,i=t.y}else n=this.mousePos.X-1,i=this.mousePos.Y-1;const o=s.default(window).width()-20,a=s.default(window).height(),l=t.width(),u=t.height(),r=n-s.default(document).scrollLeft(),c=i-s.default(document).scrollTop();return a-u<c&&(c>u?i-=u-10:i+=a-u-c),o-l<r&&(r>l?n-=l-10:o-l-r<s.default(document).scrollLeft()?n=s.default(document).scrollLeft():n+=o-l-r),{left:n+"px",top:i+"px"}}drawMenu(t,e){let s="";for(const n of Object.values(t))if("item"===n.type)s+=u.drawActionItem(n);else if("divider"===n.type)s+='<li role="separator" class="list-group-item list-group-item-divider"></li>';else if("submenu"===n.type||n.childItems){s+='<li role="menuitem" aria-haspopup="true" class="list-group-item list-group-item-submenu" tabindex="-1"><span class="list-group-item-icon">'+n.icon+"</span> "+n.label+'&nbsp;&nbsp;<span class="fa fa-caret-right"></span></li>';s+='<div class="context-menu contentMenu'+(e+1)+'" style="display:none;"><ul role="menu" class="list-group">'+this.drawMenu(n.childItems,1)+"</ul></div>"}return s}hide(t){s.default(t).hide();const e=this.eventSources.pop();e&&s.default(e).focus()}hideAll(){this.hide("#contentMenu0"),this.hide("#contentMenu1")}}return new u}));