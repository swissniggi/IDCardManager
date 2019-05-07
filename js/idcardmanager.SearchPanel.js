/* global kijs, this */

// --------------------------------------------------------------
// kijs.gui.FormPanel
// --------------------------------------------------------------
idcardmanager.SearchPanel = class idcardmanager_SearchPanel extends kijs.gui.FormPanel {


    // --------------------------------------------------------------
    // CONSTRUCTOR
    // --------------------------------------------------------------
    constructor(config={}) {
        super();
        
        this._scope = null;

        // Config generieren
        config = Object.assign({}, this._createConfig(), config);

        // Mapping für die Zuweisung der Config-Eigenschaften
        Object.assign(this._configMap, {
            rpc: { target: 'rpc' },
            scope: { target: 'scope', context: this._scope }
        });

        // Config anwenden
        if (kijs.isObject(config)) {
            config = Object.assign({}, this._defaultConfig, config);
            this.applyConfig(config, true);
        }
    }


    // --------------------------------------------------------------
    // GETTERS / SETTERS
    // --------------------------------------------------------------
    get rpc() { return this._rpc;}
    set rpc(val) {
        if (val instanceof kijs.gui.Rpc) {
            this._rpc = val;

        } else if (kijs.isString(val)) {
            if (this._rpc) {
                this._rpc.url = val;
            } else {
                this._rpc = new kijs.gui.Rpc({
                    url: val
                });
            }

        } else {
            throw new Error(`Unkown format on config "rpc"`);

        }
    }
    
    get scope() { return this._scope; }
    set scope(val) { this._scope = val; }


    // --------------------------------------------------------------
    // MEMBERS
    // --------------------------------------------------------------
    /**
     * Validiert das Formular (Validierung nur im JavaScript)
     * @returns {Boolean}
     */
    validate() {
        let sName = this._scope._viewport.down('name').value;
        let sFirstName = this._scope._viewport.down('firstName').value;
        let sEmployeeId = this._scope._viewport.down('employeeId').value;
        let sValidDate = this._scope._viewport.down('valid').value;
        
        if (sName !== '' || sFirstName !== '' || sEmployeeId !== '' || sValidDate) {
            let data = {
                lastName : sName,
                firstName : sFirstName,
                employeeId : sEmployeeId,
                validDate : sValidDate
            };
            this._scope._userDataView.load(data);

            // Suchfelder leeren
            this._scope._viewport.down('name').value = '';
            this._scope._viewport.down('firstName').value = '';
            this._scope._viewport.down('employeeId').value = '';
            this._scope._viewport.down('valid').value = '';
        } else {
            kijs.gui.MsgBox.alert('Achtung','Mindestens ein Feld muss ausgefüllt werden!');
        }
    }
    
    // PROTECTED
    _createConfig() {
        const config = {
            caption: 'Suche',
            collapsible: 'left',
            width: 280,
            defaults:{
                style:{
                    margin: '10px'
                }
            },                       
            elements:[
                {
                    xtype: 'kijs.gui.field.Text',
                    name: 'name',
                    label: 'Name',
                    labelWidth: 100,
                    required: true
                },{
                    xtype: 'kijs.gui.field.Text',
                    name: 'firstName',
                    label: 'Vorname',
                    labelWidth: 100,
                    required: true
                },{
                    xtype: 'kijs.gui.field.Number',
                    name: 'employeeId',
                    label: 'Personalnr.',
                    labelWidth: 100,
                    minValue: 0,
                    required: true
                },{
                    xtype: 'kijs.gui.field.DateTime',
                    name: 'valid',
                    label: 'Gültig bis',
                    labelWidth: 100,
                    hasTime: false,
                    required: true
                },{
                    xtype: 'kijs.gui.Icon',
                    cls: 'help-icon',
                    iconChar: '&#xf059',
                    toolTip: 'Mindestens ein Feld muss ausgefüllt werden.'
                            +'<br />Das Zeichen &#x2731; kann bei Name und'
                            +' Vorname als Wildcard eingesetzt werden.',
                    style:{
                        margin: '0 10px 0 10px',
                        color: '#4398dd'
                    }
                },{
                    xtype: 'kijs.gui.Button',
                    name: 'btnSearch',
                    isDefault: true,
                    width: 100,
                    height: 30,
                    caption: 'Suchen',
                    on:{
                        click: this._onBtnSearchClick,
                        context: this
                    },
                    style:{
                        margin: '0 10px 10px 10px',
                        float: 'right'
                    }
                }
            ]
        };
        
        return config;
    }
    
    // LISTENERS
    _onBtnSearchClick(e) {
        this.validate();
    }

    // --------------------------------------------------------------
    // DESTRUCTOR
    // --------------------------------------------------------------
    destruct(superCall) {
        if (!superCall) {
            // unrender
            this.unrender(superCall);

            // Event auslösen.
            this.raiseEvent('destruct');
        }

        // Basisklasse entladen
        super.destruct(true);
    }

};