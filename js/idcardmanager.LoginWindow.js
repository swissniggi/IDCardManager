
/* global this, kijs, idcardmanager */

// --------------------------------------------------------------
// idcardmanager.LoginWindow
// --------------------------------------------------------------

idcardmanager.LoginWindow = class idcardmanager_LoginWindow extends kijs.gui.Window  {

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
            caption: 'Login',
            iconChar: '&#xf023',
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
            name: 'loginFormPanel',
            defaults:{
                width: 280,
                height: 25,
                style:{
                    margin: '10px'
                }
            },
            elements:[
                {
                    xtype: 'kijs.gui.field.Text',
                    labelWidth: 90,
                    label: 'Benutzername',
                    name: 'username',
                    required: true
                },{
                    xtype: 'kijs.gui.field.Password',
                    labelWidth: 90,
                    label: 'Passwort',
                    name: 'password',
                    required: true
                }
            ],
            footerStyle:{
                padding: '10px'
            },
            footerElements:[
                {
                    xtype: 'kijs.gui.Button',
                    caption: 'Login',
                    name: 'btnLogin',
                    isDefault: true,
                    iconChar: '&#xf00c',
                    height: 30,
                    on:{
                        click: this._onBtnLoginClick,
                        context: this
                    }
                }
            ]
        });
    }
    
    // LISTENERS
    _onBtnLoginClick(e) {
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