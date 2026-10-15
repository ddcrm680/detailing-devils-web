(function(){
  const form=window.$('#form');
  if(!form)return;
  const note=window.$('#note'),btn=form.querySelector('button[type=submit]');
  let enquiryPending=false;
  form.addEventListener('submit',async e=>{
    e.preventDefault();
    if(enquiryPending)return;
    window.resetValidationErrors(form);
    note.textContent='';
    const n=form.querySelector('#n').value.trim(),p=form.querySelector('#p').value.trim();
    if(!n||!p){
      window.showValidationErrors(form,{
        ...(!n&&{name:['Add your name so the studio can call you.']}),
        ...(!p&&{phone:['Add your phone number so the studio can call you.']}),
      });
      return;
    }
    enquiryPending=true;btn.disabled=true;note.textContent='Sending…';
    try{
      const {data}=await axios.post(form.action,new FormData(form),{skipGlobalErrorHandler:true});
      note.textContent='';
      if(data?.success===true){
        form.reset();
        window.handleResponseSuccess({title:'Enquiry sent',message:data.message||'Thank you. Our team will call you shortly.'});
      }else{
        window.showNotification({type:'error',title:'Unable to send enquiry',html:data?.message||'Something went wrong. Please call us instead.'});
      }
    }catch(err){
      note.textContent='';
      if(err.response?.status===503){
        window.showNotification({type:'error',title:'Enquiry unavailable',html:err.response.data?.message||'Enquiry submissions are temporarily unavailable. Please try again later.'});
      }else{
        await window.handleResponseError(err,form);
      }
    }
    finally{enquiryPending=false;btn.disabled=false}
  });
})();
