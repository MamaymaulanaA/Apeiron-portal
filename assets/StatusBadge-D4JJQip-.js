import{c as t,g as i,C as h,s as u}from"./index-BLqM3-qE.js";import{o as c,b as y,j as k,k as m,p as d,t as C,n as v,c as a}from"./vue-DEkLOxXO.js";/**
 * @license lucide-vue-next v0.469.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const o=t("CircleIcon",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}]]);/**
 * @license lucide-vue-next v0.469.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const g=t("ClockIcon",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["polyline",{points:"12 6 12 12 16 14",key:"68esgv"}]]);/**
 * @license lucide-vue-next v0.469.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const S=t("ServerCrashIcon",[["path",{d:"M6 10H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2",key:"4b9dqc"}],["path",{d:"M6 14H4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2h-2",key:"22nnkd"}],["path",{d:"M6 6h.01",key:"1utrut"}],["path",{d:"M6 18h.01",key:"uhywen"}],["path",{d:"m13 6-4 6h6l-4 6",key:"14hqih"}]]),M={__name:"StatusBadge",props:{status:{type:String,required:!0},label:{type:String,default:""}},setup(r){const s=r,n={Clock:g,CircleCheck:h,CircleX:i,ServerCrash:S,Circle:o},e=a(()=>u(s.status)),l=a(()=>s.label||e.value.label),p=a(()=>n[e.value.icon]||o);return(_,b)=>(c(),y("span",{class:v(["badge",e.value.tone])},[(c(),k(m(p.value),{size:13,"stroke-width":2.4})),d(" "+C(l.value),1)],2))}};export{g as C,S,M as _};
