
/* global kijs, idcardmanager */

idcardmanager.App = class idcardmanager_App { 

    constructor(config={}) {

            this._userDataView = null;
            this._loginWindow = null;
            this._viewport = null;

            // RPC-Instanz
            var rpcConfig = {};
            if (config.ajaxUrl) {
                rpcConfig.url = config.ajaxUrl;
            }
            this._rpc = new kijs.gui.Rpc(rpcConfig);
    }
    
    // --------------------------------------------------------------
    // MEMBERS
    // --------------------------------------------------------------
    
    runApp() {
        this._userDataView = new idcardmanager.UserDataView({
            rpc: this._rpc
        });
        
        const mainPanel = this.createMainPanel();

        // ViewPort erstellen
        this._viewport = new kijs.gui.ViewPort({
            cls: 'kijs-flexcolumn',
            elements:[
                mainPanel
            ]
        });
        
        // feststellen ob ein Benutzer angemeldet ist
        this._rpc.do('idcardmanager.checkLogin', null, 
        function(response) {
            if (response.username !== false) {
                sessionStorage.setItem('Benutzer', response.username);

                // Caption des Logout-Buttons setzen
                this._viewport.render();
                let sCaption = 'angemeldet als ' + sessionStorage.getItem('Benutzer') + '&nbsp;';
                mainPanel.headerBar.downX('kijs.gui.Button').caption = sCaption;
            } else {
                this.showLoginWindow();
            }
        }, this, false, this._viewport, 'dom', false);
    }
    
    createMainPanel() {
        return new kijs.gui.Panel({
            name: 'mainPanel',
            caption: 'IDCardManager',
            footerCaption: '&copy; 2019 by Nicolas Burgunder',
            cls: 'kijs-flexrow',
            style:{
                flex: 1
            },
            headerBarStyle:{
                marginBottom: '3px'
            },
            headerBarElements:{
                xtype: 'kijs.gui.Button',
                name: 'btnLogout',
                iconChar: '&#xf011',
                toolTip: 'ausloggen',
                style:{
                    marginRight: '6px',
                    display: 'flex',
                    flexDirection: 'row-reverse'
                },
                on:{
                    click: this._onBtnLogoutClick,
                    context: this
                }
            },
            elements:[                
            {
                xtype: 'kijs.gui.Container',
                cls: 'kijs-flexrow',
                style: {
                    flex: 1
                },
                elements: [
                    // LEFT
                    {
                        xtype: 'kijs.gui.Panel',
                        caption: 'Suche',
                        collapsible: 'left',
                        width: 240,
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
                                labelWidth: 80
                            },{
                                xtype: 'kijs.gui.field.Text',
                                name: 'firstName',
                                label: 'Vorname',
                                labelWidth: 80
                            },{
                                xtype: 'kijs.gui.field.Number',
                                name: 'employeeId',
                                label: 'Mitarbeiternr.',
                                labelWidth: 80,
                                minValue: 0
                            },{
                                xtype: 'kijs.gui.field.DateTime',
                                name: 'valid',
                                label: 'Gültig bis',
                                labelWidth: 80,
                                hasTime: false
                            },{
                                xtype: 'kijs.gui.Icon',
                                cls: 'help-icon',
                                height: 18,
                                width: 18,
                                iconChar: '&#xf059',
                                toolTip: 'Mindestens ein Feld muss ausgefüllt werden.'
                                        +'<br />Das Zeichen &#x2731; kann als Wildcard eingesetzt werden.',
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
                    },{
                        xtype: 'kijs.gui.Splitter',
                        targetPos: 'left'
                    },{
                        xtype: 'kijs.gui.Panel',
                        caption: 'Ergebnisse',
                        style: {
                            flex: 1
                        },
                        innerStyle: {
                            overflowY: 'auto'
                        },
                        headerElements:[
                            {
                                xtype: 'kijs.gui.Button',
                                iconChar: '&#xf02f',
                                toolTip: 'Druckvorlage erstellen',
                                on:{
                                    click: this._onBtnPrintClick,
                                    context: this
                                }
                            }
                        ],
                        elements:[
                            //this._userDataView 
                        ]
                    }
                ]
            }]
        });
    }
    
    showLoginWindow() {
        this._loginWindow = new idcardmanager.LoginWindow({
            rpc: this._rpc,
            facadeFnSave: 'idcardmanager.loginUser',
            on:{
                afterSave: this._onLoginWindowAfterSave,
                context: this
            }
        });
        this._loginWindow.show();
    }
    
    //LISTENERS
    _onBtnLogoutClick() {
        
    }
    
    _onBtnPrintClick() {
        
    }
    
    _onBtnSearchClick() {
        
    }
    
    _onLoginWindowAfterSave() {
        let sUsername = this._loginWindow.down('username').value;
        sessionStorage.setItem('Benutzer', sUsername);
        
        // Caption des Logout-Buttons setzen
        this._viewport.render();
        let sCaption = 'angemeldet als ' + sUsername + '&nbsp;';
        //this._viewport.down('mainPanel').headerBar.down('btnLogout').caption = sCaption; 
        this._loginWindow.destruct();
    }
};