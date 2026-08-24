import{c as r,s as i,k as h,C as u}from"./index-DPXP5AkW.js";import{C as m}from"./clock-BB77xdQm.js";import{o as s,b as d,j as k,k as C,p as y,t as v,n as S,c as a}from"./vue-CsQ4Gd9G.js";/**
 * @license lucide-vue-next v0.469.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const c=r("CircleIcon",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}]]);/**
 * @license lucide-vue-next v0.469.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const _=r("ServerCrashIcon",[["path",{d:"M6 10H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2",key:"4b9dqc"}],["path",{d:"M6 14H4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2h-2",key:"22nnkd"}],["path",{d:"M6 6h.01",key:"1utrut"}],["path",{d:"M6 18h.01",key:"uhywen"}],["path",{d:"m13 6-4 6h6l-4 6",key:"14hqih"}]]),M={__name:"StatusBadge",props:{status:{type:String,required:!0},label:{type:String,default:""}},setup(o){const t=o,n={Clock:m,CircleCheck:u,CircleX:h,ServerCrash:_,Circle:c},e=a(()=>i(t.status)),l=a(()=>t.label||e.value.label),p=a(()=>n[e.value.icon]||c);return(b,g)=>(s(),d("span",{class:S(["badge",e.value.tone])},[(s(),k(C(p.value),{size:13,"stroke-width":2.4})),y(" "+v(l.value),1)],2))}};export{_ as S,M as _};
