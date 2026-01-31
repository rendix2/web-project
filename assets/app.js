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
                new HappyPlugin(new Happy()),
                new SortablePlugin(new SortableJS()),
                new DatepickerPlugin(new VanillaDatepicker({ buttonClass: 'btn' })),
                new SelectpickerPlugin(new TomSelect(Select)),
                new TreeViewPlugin(),
            ],
        },
    });
});