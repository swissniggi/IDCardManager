
/* global this, kijs, idcardmanager */

// --------------------------------------------------------------
// idcardmanager.EditorWindow
// --------------------------------------------------------------

idcardmanager.EditorWindow = class idcardmanager_EditorWindow extends kijs.gui.Window  {

    // --------------------------------------------------------------
    // CONSTRUCTOR
    // --------------------------------------------------------------
    constructor(config={}) {
        super();

        this._formPanel = null;

        // Config generieren
        config = Object.assign({}, this._createConfig(), config);

         // Mapping für die Zuweisung der Config-Eigenschaften
        Object.assign(this._configMap, {
            facadeFnSave: { target: 'facadeFnSave', context: this._formPanel },
            rpc: { target: 'rpc', context: this._formPanel }
        });

        // Event-Weiterleitungen von this._formPanel
        this._eventForwardsAdd('afterSave', this._formPanel);

        // Config anwenden
        if (kijs.isObject(config)) {
            config = Object.assign({}, this._defaultConfig, config);
            this.applyConfig(config, true);
        }
    }


    // --------------------------------------------------------------
    // GETTERS / SETTERS
    // --------------------------------------------------------------
    get facadeFnSave() { return this._formPanel.facadeFnSave; }
    set facadeFnSave(val) { this._formPanel.facadeFnSave = val; }

    get rpc() { return this._formPanel.rpc; }
    set rpc(val) { this._formPanel.rpc = val; }


    // --------------------------------------------------------------
    // MEMBERS
    // --------------------------------------------------------------
    // PROTECTED
    _createConfig() {
        this._formPanel = this._createFormPanel();
        const config = {
            caption: 'Editor',
            iconChar: '&#xf044',
            width: 300,
            closable: false,
            maximizable: false,
            resizable: false,
            modal: true,
            elements:[
                this._formPanel
            ]
        };
        
        return config;
    }
    
    _createFormPanel() {
        return new kijs.gui.FormPanel({
            facadeFnSave: 'idcardmanager.updateUserData',
            facadeFnLoad: 'idcardmanager.loadEditorData',
            name: 'editorFormPanel',
            defaults:{
                width: 280,
                height: 25,
                style:{
                    margin: '10px'
                }
            },
            elements:[
                {
                    xtype: kijs.gui.field.Text,
                    labelWidth: 90,
                    label: 'Vorname, Name',
                    name: 'name',
                    required: true
                },{
                    xtype: kijs.gui.field.Text,
                    labelWidth: 90,
                    label: 'Funktion',
                    name: 'title',
                    required: true
                },{
                    xtype: kijs.gui.field.DateTime,
                    labelWidth: 90,
                    label: 'Gültig bis:',
                    name: 'valid',
                    required: true,
                    hasTime: false
                },{
                    xtype: kijs.gui.field.Number,
                    labelWidth: 90,
                    label: 'Ausweisnummer',
                    name: 'employeeId',
                    required: true,
                    minValue: 0
                }
            ],
            footerStyle:{
                padding: '10px'
            },
            footerElements:[
                {
                    xtype: kijs.gui.Button,
                    caption: 'Speichern',
                    name: 'btnSave',
                    isDefault: true,
                    iconChar: '&#xf00c',
                    width: 100,
                    height: 30,
                    on:{
                        click: this._onBtnSaveClick,
                        context: this
                    }
                }
            ]
        });
    }
    
    // LISTENERS
    _onBtnSaveClick(e) {
        this._formPanel.save();
    }


    // --------------------------------------------------------------
    // DESTRUCTOR
    // --------------------------------------------------------------
    destruct(superCall) {
        if (!superCall) {
            // Event auslösen.
            this.raiseEvent('destruct');
        }

        this._formPanel = null;

        // Basisklasse auch entladen
        super.destruct(true);
    }
};