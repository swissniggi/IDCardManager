/* global kijs, this */

// --------------------------------------------------------------
// idcarmanager.UserDataView
// --------------------------------------------------------------
idcardmanager.UserDataView = class idcardmanager_UserDataView extends kijs.gui.DataView {


    // --------------------------------------------------------------
    // CONSTRUCTOR
    // --------------------------------------------------------------
    constructor(config={}) {
        super(false);

        // Config generieren
        config = Object.assign({}, this._createConfig(), config);

        // Config anwenden
        if (kijs.isObject(config)) {
            this.applyConfig(config, true);
        }
        
        // Events
        //this.on('elementDblClick', this._onElementDblClick, this);
    }


    // --------------------------------------------------------------
    // GETTERS / SETTERS
    // --------------------------------------------------------------


    // --------------------------------------------------------------
    // MEMBERS
    // --------------------------------------------------------------
    /**
     * Erstellt aus einem Recordset ein getDataViewElement
     * @param {Array} dataRow   Datensatz, der gerendert werden soll
     * @param {Number} index    Index des Datensatzes. Die Datensätze werden durchnummeriert 0 bis ...
     * @returns {kijs.gui.getDataViewElement}
     */
    createElement(dataRow, index) {
        let html = '';

        html += '<div class="outerdiv"><div>';
        html += ' <span class="label">'+ dataRow['lastName'] + ' ' + dataRow['firstName'] + '</span>';
        html += '</div>';
        
        html += '<div>';
        html += ' <span class="label">'+ dataRow['title'] + '</span>';
        html += '</div>';
        
        html += '<div>';
        html += ' <span class="label">'+ dataRow['valid'] + '</span>';
        html += '</div>';
        
        html += '<div>';
        html += ' <span class="label">'+ dataRow['employeeId'] + '</span>';
        html += '</div></div>';
        
        html += '<img class="portrait" src="' + dataRow['imgPath'] + '" alt="Kein Bild gefunden."></img>';

        return new kijs.gui.DataViewElement({
            dataRow: dataRow,
            html: html
        });
    }

    // PROTECTED
    _createConfig() {
        const config = {
            name: 'dvUserData',
            selectType: 'simple',
            waitMaskTargetDomProperty: 'innerDom',
            autoLoad: false,
            facadeFnLoad: 'idcardmanager.searchUsers',
            style:{
                width: '100%'
            },
            innerStyle:{
                padding: '10px',
                overflowY: 'auto',
                flex: 'initial'
            }
        };

        return config;
    }


    // --------------------------------------------------------------
    // DESTRUCTOR
    // --------------------------------------------------------------
    destruct(superCall) {
        if (!superCall) {
            // Event auslösen.
            this.raiseEvent('destruct');
        }

        // Basisklasse entladen
        super.destruct(true);
    }

};