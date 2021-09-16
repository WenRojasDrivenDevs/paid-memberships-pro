/**
 * Block: PMPro Membership Confirmation
 *
 * Displays the Membership Confirmation template.
 *
 */

import blockJSON from './block.json';

 /**
  * Internal block libraries
  */
 const { __ } = wp.i18n;
 const {
    registerBlockType
} = wp.blocks;

 /**
  * Register block
  */
 export default registerBlockType(
     blockJSON,
     {
         title: __( 'Membership Confirmation Page', 'paid-memberships-pro' ),
         description: __( 'Displays the member\'s Membership Confirmation after Membership Checkout.', 'paid-memberships-pro' ),
         icon: {
            background: '#2997c8',
            foreground: '#ffffff',
            src: 'yes',
         },
         edit(){
             return [
                <div className="pmpro-block-element">
                   <span className="pmpro-block-title">{ __( 'Paid Memberships Pro', 'paid-memberships-pro' ) }</span>
                   <span className="pmpro-block-subtitle">{ __( 'Membership Confirmation Page', 'paid-memberships-pro' ) }</span>
                </div>
            ];
         },
         save() {
           return null;
         },
       }
 );
