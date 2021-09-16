/**
 * Block: PMPro Membership
 *
 *
 */

import metadata from './block.json';

 /**
  * Internal block libraries
  */
 const { __ } = wp.i18n;
 const {
    registerBlockType,
} = wp.blocks;
const {
    PanelBody,
    CheckboxControl,
} = wp.components;
const {
    InspectorControls,
    InnerBlocks,
} = wp.blockEditor;

const all_levels = [{ value: 0, label: "Non-Members" }].concat( pmpro.all_level_values_and_labels );

 /**
  * Register block
  */
 export default registerBlockType(
     metadata,
     {
         icon: {
            background: '#2997c8',
            foreground: '#ffffff',
            src: metadata.icon,
         },
         edit: props => {
             const { attributes: {levels, uid}, setAttributes, isSelected } = props;
             if( uid=='' ) {
               var rand = Math.random()+"";
               setAttributes( { uid:rand } );
             }

             // Build an array of checkboxes for each level.
             var checkboxes = all_levels.map( function(level) {
                 function setLevelsAttribute( nowChecked ) {
                     if ( nowChecked && ! ( levels.some( levelID => levelID == level.value ) ) ) {
                        // Add the level.
                        const newLevels = levels.slice();
                        newLevels.push( level.value + '' );
                        setAttributes( { levels:newLevels } );
                     } else if ( ! nowChecked && levels.some( levelID => levelID == level.value ) ) {
                        // Remove the level.
                        const newLevels = levels.filter(( levelID ) => levelID != level.value);
                        setAttributes( { levels:newLevels } );
                     }
                 }
                 return [
                    <CheckboxControl
                        label = { level.label }
                        checked = { levels.some( levelID => levelID == level.value ) }
                        onChange = { setLevelsAttribute }
                    />
                 ]
             });

             return [
                isSelected && <InspectorControls>
                    <PanelBody>
                        <div class="pmpro-block-inspector-scrollable">
                            {checkboxes}
                        </div>
                    </PanelBody>
                </InspectorControls>,
                isSelected && <div className="pmpro-block-require-membership-element" >
                  <span className="pmpro-block-title">{ __( 'Require Membership', 'paid-memberships-pro' ) }</span>
                  <PanelBody>
                      {checkboxes}
                  </PanelBody>
                  <InnerBlocks
                      renderAppender={ () => (
                        <InnerBlocks.ButtonBlockAppender />
                      ) }
                      templateLock={ false }
                  />
                </div>,
                ! isSelected && <div className="pmpro-block-require-membership-element" >
                  <span className="pmpro-block-title">{ __( 'Require Membership', 'paid-memberships-pro' ) }</span>
                  <InnerBlocks
                      renderAppender={ () => (
                        <InnerBlocks.ButtonBlockAppender />
                      ) }
                      templateLock={ false }
                  />
                </div>,
            ];
         },
         save: props => {
           const {  className } = props;
        		return (
        			<div className={ className }>
        				<InnerBlocks.Content />
        			</div>
        		);
        	},
       }
 );
