/* Progressive visual editor. JSON remains available as the single form schema. */
document.querySelectorAll('textarea[name="fields"]').forEach((area)=>{
 let fields;try{fields=JSON.parse(area.value);}catch(e){return;}if(!Array.isArray(fields))return;
 const box=document.createElement('section');const list=document.createElement('div');const add=document.createElement('button');add.type='button';add.textContent='Añadir campo';box.append(list,add);area.parentElement.before(box);
 const types=['text','email','tel','url','date','number','textarea','select','checkbox','checkboxes'];
 const sync=()=>{area.value=JSON.stringify(fields,null,2);};
 const render=()=>{list.replaceChildren();fields.forEach((field,index)=>{
  const row=document.createElement('fieldset');const legend=document.createElement('legend');legend.textContent=(index+1)+'. '+(field.label||'Campo');row.append(legend);
  const control=(label,value,change)=>{const l=document.createElement('label');l.textContent=label;const i=document.createElement('input');i.value=value??'';i.addEventListener('input',()=>{change(i.value);sync();});l.append(i);row.append(l);return i;};
  control('Nombre interno',field.key,v=>field.key=v);control('Etiqueta',field.label,v=>{field.label=v;legend.textContent=(index+1)+'. '+v;});
  const select=document.createElement('select');types.forEach(type=>{const option=document.createElement('option');option.value=type;option.textContent=type;option.selected=type===field.type;select.append(option);});const typeLabel=document.createElement('label');typeLabel.textContent='Tipo';typeLabel.append(select);row.append(typeLabel);select.addEventListener('change',()=>{field.type=select.value;sync();render();});
  const l=document.createElement('label');const required=document.createElement('input');required.type='checkbox';required.checked=!!field.required;required.addEventListener('change',()=>{field.required=required.checked;sync();});l.append(required,document.createTextNode(' Obligatorio'));row.append(l);
  if(['select','checkboxes'].includes(field.type)){const choices=document.createElement('textarea');choices.value=(field.options||[]).join('\n');choices.addEventListener('input',()=>{field.options=choices.value.split('\n').filter(v=>v.trim()!=='');sync();});const l=document.createElement('label');l.textContent='Opciones (una por línea)';l.append(choices);row.append(l);}
  [['Subir',()=>{if(index>0)[fields[index-1],fields[index]]=[fields[index],fields[index-1]];}],['Bajar',()=>{if(index+1<fields.length)[fields[index+1],fields[index]]=[fields[index],fields[index+1]];}],['Quitar',()=>fields.splice(index,1)]].forEach(([label,action])=>{const b=document.createElement('button');b.type='button';b.textContent=label;b.style.margin='6px';b.addEventListener('click',()=>{action();sync();render();});row.append(b);});list.append(row);
 });};
 add.addEventListener('click',()=>{fields.push({key:'field_'+Date.now(),type:'text',label:'Nuevo campo',required:false});sync();render();});
 area.addEventListener('change',()=>{try{const next=JSON.parse(area.value);if(Array.isArray(next)){fields=next;render();}}catch(e){}});render();
});
