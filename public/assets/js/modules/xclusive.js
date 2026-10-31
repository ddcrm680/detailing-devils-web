(() => {
  const form = window.$('#fofo-form');
  if (form?.dataset.franchiseModel !== 'xclusive') return;

  window.initFranchiseForm(form, {
    conditionals: [
      ['bussinessowner', 'fofo-business-details'],
      ['experienceautomotive', 'fofo-experience-details'],
      ['existingautomotive', 'fofo-facility-details'],
      ['spacearound', 'fofo-property-details'],
    ],
    validateQuestion(input, { form }) {
      if (input.type === 'radio' && input.name === 'spendfranchise') {
        return form.querySelector('input[name="spendfranchise"]:checked')
          ? '' : 'Choose how much time you can spend.';
      }
      return null;
    },
  });
})();
