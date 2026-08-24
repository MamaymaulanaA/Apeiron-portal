import{c as i,s as p,k as u,C as m}from"./index-X2JOzffa.js";import{S as C,C as k}from"./server-crash-Bc_KQnk1.js";import{o as s,b as d,j as v,k as y,p as S,t as _,n as g,c as a}from"./vue-CsQ4Gd9G.js";/**
 * @license lucide-vue-next v0.469.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const c=i("CircleIcon",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}]]),I={__name:"StatusBadge",props:{status:{type:String,required:!0},label:{type:String,default:""}},setup(o){const t=o,r={Clock:k,CircleCheck:m,CircleX:u,ServerCrash:C,Circle:c},e=a(()=>p(t.status)),l=a(()=>t.label||e.value.label),n=a(()=>r[e.value.icon]||c);return(b,f)=>(s(),d("span",{class:g(["badge",e.value.tone])},[(s(),v(y(n.value),{size:13,"stroke-width":2.4})),S(" "+_(l.value),1)],2))}};export{I as _};
