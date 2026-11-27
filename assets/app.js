import naja from "naja";
import netteForms from "nette-forms";
import {
    AutosubmitPlugin,
    CheckboxPlugin,
    ConfirmPlugin,
    createDatagrids,
    DatepickerPlugin,
    InlinePlugin,
    ItemDetailPlugin,
    NetteFormsPlugin,
    SelectpickerPlugin,
    SortableJS,
    SortablePlugin,
    TomSelect,
    TreeViewPlugin,
    VanillaDatepicker
} from "../vendor/ublaboo/datagrid/assets/index.ts"
import { NajaAjax } from "../vendor/ublaboo/datagrid/assets/ajax/index.ts";
import Select from "tom-select";
import { Dropdown } from "bootstrap";

// Styles
import './app.css';

//netteForms.initOnLoad();
//naja.initialize();

// Datagrid + UI
document.addEventListener("DOMContentLoaded", () => {
    // Show / hide the password (PasswordFormControl). No inline script: the Content-Security-Policy blocks it
    document.querySelectorAll('[data-password-toggle]')
        .forEach(el => el.addEventListener('click', () => {
            const input = document.getElementById(el.getAttribute('data-password-target'));
            input.type = input.type === 'password' ? 'text' : 'password';
        }))

    // Initialize dropdowns
    Array.from(document.querySelectorAll('.dropdown'))
        .forEach(el => new Dropdown(el))

    // Initialize Naja (nette ajax)
    naja.formsHandler.netteForms = netteForms;
    naja.initialize();

    // Initialize datagrids
    createDatagrids(new NajaAjax(naja), {
        datagrid: {
            plugins: [
                new AutosubmitPlugin(),
                new CheckboxPlugin(),
                new ConfirmPlugin(),
                new InlinePlugin(),
                new ItemDetailPlugin(),
                new NetteFormsPlugin(netteForms),
                new SortablePlugin(new SortableJS()),
                new DatepickerPlugin(new VanillaDatepicker({ buttonClass: 'btn' })),
                // the dropdown hangs on <body>: inside the grid it was clipped by .table-responsive (overflow: auto),
                // the last options of a filter in the header of an empty / short grid could not be seen
                new SelectpickerPlugin(new TomSelect(Select, {dropdownParent: 'body'})),
                new TreeViewPlugin(),
            ],
        },
    });
});