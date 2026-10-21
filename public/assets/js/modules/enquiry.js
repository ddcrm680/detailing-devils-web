(function(){
  const form=window.$('#form');
  if(!form)return;
  const note=window.$('#note'),btn=form.querySelector('button[type=submit]');
  const phoneInput=form.querySelector('#p');
  const phoneIti=window.intlTelInput?.(phoneInput,{
    initialCountry:window.history.state?.enquiryPhoneCountry||'in',
    separateDialCode:true,
    strictMode:true,
    numberDisplayFormat:'NATIONAL',
    containerClass:'enquiry-phone',
    // Escape the contact section's clipping and the form's backdrop filter.
    dropdownParent:document.body,
    loadUtils:()=>import('https://cdn.jsdelivr.net/npm/intl-tel-input@29.1.2/dist/js/utils.js'),
  });
  let phoneReady=false,phoneUnavailable=!phoneIti;
  function rememberPhoneCountry(){
    window.history.replaceState({...window.history.state,enquiryPhoneCountry:phoneIti.getSelectedCountry().iso2},'');
  }
  function resizePhoneDropdown(){
    const dropdown=document.querySelector('.enquiry-phone .iti__country-selector');
    if(!dropdown)return;
    // Let the country list scroll independently of the homepage's Lenis instance.
    dropdown.setAttribute('data-lenis-prevent','');
    if(dropdown.closest('.iti--inline-country-selector'))dropdown.style.width=`${phoneInput.offsetWidth}px`;
  }
  if(phoneIti){
    phoneIti.promise.then(()=>{phoneReady=true;rememberPhoneCountry()}).catch(()=>{phoneUnavailable=true});
    phoneInput.addEventListener('countrychange',rememberPhoneCountry);
    phoneInput.addEventListener('open:countryselector',resizePhoneDropdown);
    window.addEventListener('resize',resizePhoneDropdown);
  }
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
    const phoneError=phoneUnavailable?'Phone verification could not load. Please reload this page.'
      :!phoneReady?'Phone verification is loading. Please try again in a moment.'
      :!phoneIti.isValidNumber()?'Enter a valid phone number for the selected country.':'';
    if(phoneError){window.showValidationErrors(form,{phone:[phoneError]});return}
    enquiryPending=true;btn.disabled=true;note.textContent='Sending…';
    try{
      const application=new FormData(form);
      application.set('phone',phoneIti.getNumber());
      const {data}=await axios.post(form.action,application,{skipGlobalErrorHandler:true});
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
