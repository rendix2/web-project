import {LiveForm, Nette} from 'live-form-validation/live-form-validation';

LiveForm.setOptions({
    messageErrorPrefix: 'Error: ',
    wait: 500
});

Nette.initOnLoad();
window.Nette = Nette;
window.LiveForm = LiveForm;