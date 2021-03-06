
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
        // Konfiguration erstellen
        this._formPanel = this._createFormPanel();
        const config = {
            caption: 'Editor',
            iconChar: '&#xf044',
            width: 320,
            closable: true,
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
        // FormPanel konfigurieren
        return new kijs.gui.FormPanel({
            facadeFnSave: 'idcardmanager.updateUserData',
            facadeFnLoad: 'idcardmanager.loadEditorData',
            name: 'editorFormPanel',
            defaults:{
                width: 300,
                height: 25,
                style:{
                    margin: '10px'
                }
            },
            elements:[
                {
                    xtype: 'kijs.gui.field.Text',
                    labelWidth: 100,
                    label: 'Name, Vorname',
                    name: 'name',
                    required: true,
                    disabled: true
                },{
                    xtype: 'kijs.gui.field.Text',
                    labelWidth: 100,
                    label: 'Funktion',
                    name: 'title',
                    required: true
                },{
                    xtype: 'kijs.gui.field.DateTime',
                    labelWidth: 100,
                    label: 'Gültig bis',
                    name: 'valid',
                    required: true,
                    hasTime: false
                },{
                    xtype: 'kijs.gui.field.Number',
                    labelWidth: 100,
                    label: 'Personalnr.',
                    name: 'employeeId',
                    required: true,
                    minValue: 0
                },{
                    xtype: 'kijs.gui.field.Text',
                    labelWidth: 100,
                    label: 'Kostenstelle.',
                    name: 'departmentNumber',
                    required: false
                },{
                    xtype: 'kijs.gui.Icon',
                    cls: 'help-icon',
                    iconChar: '&#xf059',
                    toolTip: 'Das Gültigkeitsdatum muss in der Zukunft liegen.',
                    style:{
                        margin: '0 10px 0 10px',
                        color: '#4398dd'
                    }
                }
            ],
            footerStyle:{
                padding: '10px'
            },
            footerElements:[
                {
                    xtype: 'kijs.gui.Button',
                    caption: 'Abbrechen',
                    name: 'btnCancel',
                    iconChar: '&#xf00d',
                    height: 30,
                    on:{
                        click: this._onBtnCancelClick,
                        context: this
                    }
                },{
                    xtype: 'kijs.gui.Button',
                    caption: 'Speichern',
                    name: 'btnSave',
                    isDefault: true,
                    iconChar: '&#xf00c',
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
    _onBtnCancelClick(e) {
        this.close();
    }
    
    _onBtnSaveClick(e) {
        let sEmployeeId = this._formPanel.down('employeeId').value;
        let sValidDate = this._formPanel.down('valid').value;
        
        // Gültigkeitsdatum validieren
        if (sValidDate !== '' && !sValidDate) {
            kijs.gui.MsgBox.error('Fehler!','Gültigkeitsdatum hat falsches Format!');
            return false;
        } else if (Date.parse(sValidDate) <= Date.parse(new Date())) {
            kijs.gui.MsgBox.error('Fehler!', 'Das Gültigkeitsdatum muss in der Zukunft liegen!');
            return false;
        }
        
        // Personalnummer validieren
        if (sEmployeeId !== '' && isNaN(sEmployeeId)) {
            kijs.gui.MsgBox.error('Fehler!','Personalnummer muss eine Zahl sein!');
            return false;
        }
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