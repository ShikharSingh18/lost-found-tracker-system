/* assets/js/upload.js - client-side convenience checks only. Server must re-validate (type, size, count, real MIME). */
(function(){
  var MAX_FILES=5, MAX_BYTES=15*1024*1024, TYPES=['image/jpeg','image/png'];
  var LABELS=['Front','Overall','Side','Label / tag','Marks / damage'];
  var input=document.getElementById('photos'), drop=document.getElementById('drop');
  var list=document.getElementById('previews'), count=document.getElementById('upl-count');
  var errBox=document.getElementById('upl-errors'), shots=document.querySelectorAll('#shots li');
  var files=[];
  function mb(b){return (b/1048576).toFixed(1)+' MB';}
  function showErrors(msgs){errBox.innerHTML=msgs.map(function(m){return '<div>'+m.replace(/</g,'&lt;')+'</div>';}).join('');errBox.classList.toggle('show',msgs.length>0);}
  function sync(){
    var dt=new DataTransfer(); files.forEach(function(f){dt.items.add(f.file);}); input.files=dt.files;
    count.textContent=files.length+' / '+MAX_FILES+' photos'; count.classList.toggle('full',files.length===MAX_FILES);
    drop.classList.toggle('disabled',files.length>=MAX_FILES); input.disabled=files.length>=MAX_FILES;
    shots.forEach(function(li,i){li.classList.toggle('done',i<files.length);});
    list.innerHTML='';
    files.forEach(function(f,i){
      var li=document.createElement('li'); li.className='pv enter';
      li.innerHTML='<img alt="Preview of photo '+(i+1)+'" src="'+f.url+'"><span class="tag">'+(i+1)+'. '+LABELS[i]+'</span>'+
        '<button type="button" class="rm" aria-label="Remove photo '+(i+1)+'">&times;</button>'+
        '<div class="info"></div>';
      li.querySelector('.info').textContent=f.file.name+' · '+mb(f.file.size);
      li.querySelector('.rm').addEventListener('click',function(){URL.revokeObjectURL(f.url);files.splice(i,1);showErrors([]);sync();});
      list.appendChild(li);
    });
  }
  function add(incoming){
    var errs=[];
    Array.prototype.forEach.call(incoming,function(file){
      if(files.length>=MAX_FILES){errs.push('Maximum '+MAX_FILES+' photos. "'+file.name+'" was not added.');return;}
      if(TYPES.indexOf(file.type)<0){errs.push('"'+file.name+'" is not a JPG or PNG.');return;}
      if(file.size>MAX_BYTES){errs.push('"'+file.name+'" is '+mb(file.size)+'. Maximum is 15 MB per photo.');return;}
      files.push({file:file,url:URL.createObjectURL(file)});
    });
    showErrors(errs); sync();
  }
  input.addEventListener('change',function(){var picked=Array.prototype.slice.call(input.files);input.value='';files=files;add(picked);});
  ['dragenter','dragover'].forEach(function(e){drop.addEventListener(e,function(ev){ev.preventDefault();drop.classList.add('over');});});
  ['dragleave','drop'].forEach(function(e){drop.addEventListener(e,function(ev){ev.preventDefault();drop.classList.remove('over');});});
  drop.addEventListener('drop',function(ev){if(files.length<MAX_FILES)add(ev.dataTransfer.files);});
  sync();
})();
