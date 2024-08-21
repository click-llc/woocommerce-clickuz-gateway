function registerClickPaymentMethod () {
    const settings = window.wc.wcSettings.getSetting( 'clickuz_data', {} )
    const label = window.wp.htmlEntities.decodeEntities( settings.title ) || window.wp.i18n.__( 'Click', 'clickuz' );
    const Content = () => {
        return window.wp.htmlEntities.decodeEntities( settings.description || '' );
    };
    const Clickuz_Gateway = {
        name: 'clickuz',
        label: label,
        content: Object( window.wp.element.createElement )( Content, null ),
        edit: Object( window.wp.element.createElement )( Content, null ),
        canMakePayment: () => true,
        ariaLabel: label,
        supports: {
            features: settings.supports,
        },
    };
    window.wc.wcBlocksRegistry.registerPaymentMethod( Clickuz_Gateway );
}

registerClickPaymentMethod();
