/**
 * Menu sorting functionality.
 * Majorly copied from WordPress Administration Navigation Menu (nav-menu.js).
 */
( function( $ ) {
	var api;
	
	/**
	 * Contains all the functions to handle WordPress navigation menus administration.
	 *
	 * @namespace wpNavMenu
	 */
	api = window.wpNavMenu = {
		
		options: {
			menuItemDepthPerLevel: 30, // Do not use directly. Use depthToPx and pxToDepth instead.
			globalMaxDepth: 11,
			sortableItems: '> *',
			targetTolerance: 0
		},
		
		menuList: undefined,	// Set in init.
		targetList: undefined, // Set in init.
		menusChanged: false,
		isRTL: !! ( 'undefined' != typeof isRtl && isRtl ), // phpcs:ignore WordPress.WhiteSpace.OperatorSpacing.NoSpaceAfter,WordPress.WhiteSpace.OperatorSpacing.NoSpaceBefore
		negateIfRTL: ( 'undefined' != typeof isRtl && isRtl ) ? -1 : 1,
		lastSearch: '',
		
		// Functions that run on init.
		init: function() {
			api.menuList = $( '#menu-to-edit' );
			api.targetList = api.menuList;
			
			this.jQueryExtensions();
			
			if ( api.menuList.length ) {
				this.initSortables();
			}
		},
		
		jQueryExtensions: function() {
			// jQuery extensions.
			$.fn.extend( {
				menuItemDepth: function() {
					var margin = api.isRTL ? this.eq( 0 ).css( 'margin-right' ) : this.eq( 0 ).css( 'margin-left' );
					return api.pxToDepth( margin && -1 != margin.indexOf( 'px' ) ? margin.slice( 0, -2 ) : 0 );
				},
				updateDepthClass: function( current, prev ) {
					return this.each( function() {
						var t = $( this );
						prev = prev || t.menuItemDepth();
						$( this ).removeClass( 'menu-item-depth-' + prev ).addClass( 'menu-item-depth-' + current );
					} );
				},
				shiftDepthClass: function( change ) {
					return this.each( function() {
						var t = $( this ),
							depth = t.menuItemDepth(),
							newDepth = depth + change;
						
						t.removeClass( 'menu-item-depth-' + depth ).addClass( 'menu-item-depth-' + ( newDepth ) );
						
						if ( 0 === newDepth ) {
							t.find( '.is-submenu' ).hide();
						}
					} );
				},
				childMenuItems: function() {
					var result = $();
					this.each( function() {
						var t = $( this ), depth = t.menuItemDepth(),
							next = t.next( '.menu-item' );
						while ( next.length && next.menuItemDepth() > depth ) {
							result = result.add( next );
							next = next.next( '.menu-item' );
						}
					} );
					return result;
				},
				shiftHorizontally: function( dir ) {
					return this.each( function() {
						var t = $( this ),
							depth = t.menuItemDepth(),
							newDepth = depth + dir;
						
						// Change .menu-item-depth-n class.
						t.moveHorizontally( newDepth, depth );
					} );
				},
				moveHorizontally: function( newDepth, depth ) {
					return this.each( function() {
						var t = $( this ),
							children = t.childMenuItems(),
							diff = newDepth - depth,
							subItemText = t.find( '.is-submenu' );
						
						// Change .menu-item-depth-n class.
						t.updateDepthClass( newDepth, depth ).updateParentMenuItemDBId();
						
						// If it has children, move those too.
						if ( children ) {
							children.each( function() {
								var t = $( this ),
									thisDepth = t.menuItemDepth(),
									newDepth = thisDepth + diff;
								t.updateDepthClass( newDepth, thisDepth ).updateParentMenuItemDBId();
							} );
						}
						
						// Show "Sub item" helper text.
						if ( 0 === newDepth ) {
							subItemText.hide();
						}
						else {
							subItemText.show();
						}
					} );
				},
				updateParentMenuItemDBId: function() {
					return this.each( function() {
						var item = $( this ),
							input = item.find( '.menu-item-data-parent-id' ),
							depth = parseInt( item.menuItemDepth(), 10 ),
							parentDepth = depth - 1,
							parent = item.prevAll( '.menu-item-depth-' + parentDepth ).first();
						
						if ( 0 === depth ) { // Item is on the top level, has no parent.
							input.val( 0 );
						}
						else { // Find the parent item, and retrieve its object id.
							input.val( parent.find( '.menu-item-data-db-id' ).val() );
						}
						
						input = item.find( '.menu-item-data-parent-type' );
						input.val( parent.find( '.menu-item-data-type' ).val() );
					} );
				},
				hideAdvancedMenuItemFields: function() {
					return this.each( function() {
						var that = $( this );
						$( '.hide-column-tog' ).not( ':checked' ).each( function() {
							that.find( '.field-' + $( this ).val() ).addClass( 'hidden-field' );
						} );
					} );
				},
				getItemData: function( itemType, id ) {
					itemType = itemType || 'menu-item';
					
					var itemData = {}, i,
						fields = [
							'menu-item-db-id',
							'menu-item-object-id',
							'menu-item-object',
							'menu-item-parent-id',
							'menu-item-position',
							'menu-item-type',
							'menu-item-title',
							'menu-item-url',
							'menu-item-description',
							'menu-item-attr-title',
							'menu-item-target',
							'menu-item-classes',
							'menu-item-xfn'
						];
					
					if ( ! id && itemType == 'menu-item' ) {
						id = this.find( '.menu-item-data-db-id' ).val();
					}
					
					if ( ! id ) return itemData;
					
					this.find( 'input' ).each( function() {
						var field;
						i = fields.length;
						while ( i-- ) {
							if ( itemType == 'menu-item' ) {
								field = fields[ i ] + '[' + id + ']';
							}
							else if ( itemType == 'add-menu-item' ) {
								field = 'menu-item[' + id + '][' + fields[ i ] + ']';
							}
							
							if (
								this.name &&
								field == this.name
							) {
								itemData[ fields[ i ] ] = this.value;
							}
						}
					} );
					
					return itemData;
				},
				setItemData: function( itemData, itemType, id ) { // Can take a type, such as 'menu-item', or an id.
					itemType = itemType || 'menu-item';
					
					if ( ! id && itemType == 'menu-item' ) {
						id = $( '.menu-item-data-db-id', this ).val();
					}
					
					if ( ! id ) return this;
					
					this.find( 'input' ).each( function() {
						var t = $( this ), field;
						$.each( itemData, function( attr, val ) {
							if ( itemType == 'menu-item' ) {
								field = attr + '[' + id + ']';
							}
							else if ( itemType == 'add-menu-item' ) {
								field = 'menu-item[' + id + '][' + attr + ']';
							}
							
							if ( field == t.attr( 'name' ) ) {
								t.val( val );
							}
						} );
					} );
					return this;
				}
			} );
		},
		
		countMenuItems: function( depth ) {
			return $( '.menu-item-depth-' + depth ).length;
		},
		
		moveMenuItem: function( $this, dir ) {
			var items, newItemPosition, newDepth,
				menuItems = $( '#menu-to-edit li' ),
				menuItemsCount = menuItems.length,
				thisItem = $this.parents( 'li.menu-item' ),
				thisItemChildren = thisItem.childMenuItems(),
				thisItemData = thisItem.getItemData(),
				thisItemDepth = parseInt( thisItem.menuItemDepth(), 10 ),
				thisItemPosition = parseInt( thisItem.index(), 10 ),
				nextItem = thisItem.next(),
				nextItemChildren = nextItem.childMenuItems(),
				nextItemDepth = parseInt( nextItem.menuItemDepth(), 10 ) + 1,
				prevItem = thisItem.prev(),
				prevItemDepth = parseInt( prevItem.menuItemDepth(), 10 ),
				prevItemId = prevItem.getItemData()[ 'menu-item-db-id' ];
			
			switch ( dir ) {
				case 'up':
					newItemPosition = thisItemPosition - 1;
					
					// Already at top.
					if ( 0 === thisItemPosition ) {
						break;
					}
					
					// If a sub item is moved to top, shift it to 0 depth.
					if ( 0 === newItemPosition && 0 !== thisItemDepth ) {
						thisItem.moveHorizontally( 0, thisItemDepth );
					}
					
					// If prev item is sub item, shift to match depth.
					if ( 0 !== prevItemDepth ) {
						thisItem.moveHorizontally( prevItemDepth, thisItemDepth );
					}
					
					// Does this item have sub items?
					if ( thisItemChildren ) {
						items = thisItem.add( thisItemChildren );
						// Move the entire block.
						items.detach().insertBefore( menuItems.eq( newItemPosition ) ).updateParentMenuItemDBId();
					}
					else {
						thisItem.detach().insertBefore( menuItems.eq( newItemPosition ) ).updateParentMenuItemDBId();
					}
					break;
				case 'down':
					// Does this item have sub items?
					if ( thisItemChildren ) {
						items = thisItem.add( thisItemChildren ),
							nextItem = menuItems.eq( items.length + thisItemPosition ),
							nextItemChildren = 0 !== nextItem.childMenuItems().length;
						
						if ( nextItemChildren ) {
							newDepth = parseInt( nextItem.menuItemDepth(), 10 ) + 1;
							thisItem.moveHorizontally( newDepth, thisItemDepth );
						}
						
						// Have we reached the bottom?
						if ( menuItemsCount === thisItemPosition + items.length ) {
							break;
						}
						
						items.detach().insertAfter( menuItems.eq( thisItemPosition + items.length ) ).updateParentMenuItemDBId();
					}
					else {
						// If next item has sub items, shift depth.
						if ( 0 !== nextItemChildren.length ) {
							thisItem.moveHorizontally( nextItemDepth, thisItemDepth );
						}
						
						// Have we reached the bottom?
						if ( menuItemsCount === thisItemPosition + 1 ) {
							break;
						}
						thisItem.detach().insertAfter( menuItems.eq( thisItemPosition + 1 ) ).updateParentMenuItemDBId();
					}
					break;
				case 'top':
					// Already at top.
					if ( 0 === thisItemPosition ) {
						break;
					}
					// Does this item have sub items?
					if ( thisItemChildren ) {
						items = thisItem.add( thisItemChildren );
						// Move the entire block.
						items.detach().insertBefore( menuItems.eq( 0 ) ).updateParentMenuItemDBId();
					}
					else {
						thisItem.detach().insertBefore( menuItems.eq( 0 ) ).updateParentMenuItemDBId();
					}
					break;
				case 'left':
					// As far left as possible.
					if ( 0 === thisItemDepth ) {
						break;
					}
					thisItem.shiftHorizontally( -1 );
					break;
				case 'right':
					// Can't be sub item at top.
					if ( 0 === thisItemPosition ) {
						break;
					}
					// Already sub item of prevItem.
					if ( thisItemData[ 'menu-item-parent-id' ] === prevItemId ) {
						break;
					}
					thisItem.shiftHorizontally( 1 );
					break;
			}
			$this.trigger( 'focus' );
			api.registerChange();
		},
		
		initSortables: function() {
			var currentDepth = 0, originalDepth, minDepth, maxDepth,
				prev, next, prevBottom, nextThreshold, helperHeight, transport,
				menuEdge = api.menuList.offset().left,
				body = $( 'body' ), maxChildDepth,
				menuMaxDepth = initialMenuMaxDepth();
			
			if ( 0 !== $( '#menu-to-edit li' ).length ) {
				$( '.drag-instructions' ).show();
			}
			
			// Use the right edge if RTL.
			menuEdge += api.isRTL ? api.menuList.width() : 0;
			
			api.menuList.sortable( {
				handle: '.menu-item-handle',
				placeholder: 'sortable-placeholder',
				items: api.options.sortableItems,
				start: function( e, ui ) {
					var height, width, parent, children, tempHolder;
					
					// Handle placement for RTL orientation.
					if ( api.isRTL ) {
						ui.item[ 0 ].style.right = 'auto';
					}
					
					transport = ui.item.children( '.menu-item-transport' );
					
					// Set depths. currentDepth must be set before children are located.
					originalDepth = ui.item.menuItemDepth();
					updateCurrentDepth( ui, originalDepth );
					
					// Attach child elements to parent.
					// Skip the placeholder.
					parent = ( ui.item.next()[ 0 ] == ui.placeholder[ 0 ] ) ? ui.item.next() : ui.item;
					children = parent.childMenuItems();
					transport.append( children );
					
					// Update the height of the placeholder to match the moving item.
					height = transport.outerHeight();
					// If there are children, account for distance between top of children and parent.
					height += ( height > 0 ) ? ( ui.placeholder.css( 'margin-top' ).slice( 0, -2 ) * 1 ) : 0;
					height += ui.helper.outerHeight();
					helperHeight = height;
					height -= 2;                                              // Subtract 2 for borders.
					ui.placeholder.height( height );
					
					// Update the width of the placeholder to match the moving item.
					maxChildDepth = originalDepth;
					children.each( function() {
						var depth = $( this ).menuItemDepth();
						maxChildDepth = ( depth > maxChildDepth ) ? depth : maxChildDepth;
					} );
					width = ui.helper.find( '.menu-item-handle' ).outerWidth(); // Get original width.
					width += api.depthToPx( maxChildDepth - originalDepth );    // Account for children.
					width -= 2;                                               // Subtract 2 for borders.
					ui.placeholder.width( width );
					
					// Update the list of menu items.
					tempHolder = ui.placeholder.next( '.menu-item' );
					tempHolder.css( 'margin-top', helperHeight + 'px' ); // Set the margin to absorb the placeholder.
					ui.placeholder.detach();         // Detach or jQuery UI will think the placeholder is a menu item.
					$( this ).sortable( 'refresh' );   // The children aren't sortable. We should let jQuery UI know.
					ui.item.after( ui.placeholder ); // Reattach the placeholder.
					tempHolder.css( 'margin-top', 0 ); // Reset the margin.
					
					// Now that the element is complete, we can update...
					updateSharedVars( ui );
				},
				stop: function( e, ui ) {
					var children, subMenuTitle,
						depthChange = currentDepth - originalDepth;
					
					// Return child elements to the list.
					children = transport.children().insertAfter( ui.item );
					
					// Add "sub menu" description.
					subMenuTitle = ui.item.find( '.item-title .is-submenu' );
					if ( 0 < currentDepth ) {
						subMenuTitle.show();
					}
					else {
						subMenuTitle.hide();
					}
					
					// Update depth classes.
					if ( 0 !== depthChange ) {
						ui.item.updateDepthClass( currentDepth );
						children.shiftDepthClass( depthChange );
						updateMenuMaxDepth( depthChange );
					}
					// Register a change.
					api.registerChange();
					// Update the item data.
					ui.item.updateParentMenuItemDBId();
					
					// Address sortable's incorrectly-calculated top in Opera.
					ui.item[ 0 ].style.top = 0;
					
					// Handle drop placement for rtl orientation.
					if ( api.isRTL ) {
						ui.item[ 0 ].style.left = 'auto';
						ui.item[ 0 ].style.right = 0;
					}
					
					// update position for each item
					var allItems = $( '.menu-item' );
					var currentPosition = 1;
					
					allItems.each( function() {
						var input = $( this ).find( '.menu-item-data-position' );
						input.val( currentPosition );
						currentPosition++;
					} );
				},
				change: function( e, ui ) {
					// Make sure the placeholder is inside the menu.
					// Otherwise fix it, or we're in trouble.
					if ( ! ui.placeholder.parent().hasClass( 'menu' ) ) {
						( prev.length ) ? prev.after( ui.placeholder ) : api.menuList.prepend( ui.placeholder );
					}
					
					updateSharedVars( ui );
				},
				sort: function( e, ui ) {
					var offset = ui.helper.offset(),
						edge = api.isRTL ? offset.left + ui.helper.width() : offset.left,
						depth = api.negateIfRTL * api.pxToDepth( edge - menuEdge );
					
					/*
					 * Check and correct if depth is not within range.
					 * Also, if the dragged element is dragged upwards over an item,
					 * shift the placeholder to a child position.
					 */
					if ( depth > maxDepth || offset.top < ( prevBottom - api.options.targetTolerance ) ) {
						depth = maxDepth;
					}
					else if ( depth < minDepth ) {
						depth = minDepth;
					}
					
					if ( depth != currentDepth ) {
						updateCurrentDepth( ui, depth );
					}
					
					// If we overlap the next element, manually shift downwards.
					if ( nextThreshold && offset.top + helperHeight > nextThreshold ) {
						next.after( ui.placeholder );
						updateSharedVars( ui );
						$( this ).sortable( 'refreshPositions' );
					}
				}
			} );
			
			function updateSharedVars( ui ) {
				var depth;
				
				prev = ui.placeholder.prev( '.menu-item' );
				next = ui.placeholder.next( '.menu-item' );
				
				// Make sure we don't select the moving item.
				if ( prev[ 0 ] == ui.item[ 0 ] ) prev = prev.prev( '.menu-item' );
				if ( next[ 0 ] == ui.item[ 0 ] ) next = next.next( '.menu-item' );
				
				prevBottom = ( prev.length ) ? prev.offset().top + prev.height() : 0;
				nextThreshold = ( next.length ) ? next.offset().top + next.height() / 3 : 0;
				minDepth = ( next.length ) ? next.menuItemDepth() : 0;
				
				if ( prev.length ) {
					maxDepth = ( ( depth = prev.menuItemDepth() + 1 ) > api.options.globalMaxDepth ) ? api.options.globalMaxDepth : depth;
				}
				else {
					maxDepth = 0;
				}
			}
			
			function updateCurrentDepth( ui, depth ) {
				ui.placeholder.updateDepthClass( depth, currentDepth );
				currentDepth = depth;
			}
			
			function initialMenuMaxDepth() {
				if ( ! body[ 0 ].className ) return 0;
				var match = body[ 0 ].className.match( /menu-max-depth-(\d+)/ );
				return match && match[ 1 ] ? parseInt( match[ 1 ], 10 ) : 0;
			}
			
			function updateMenuMaxDepth( depthChange ) {
				var depth, newDepth = menuMaxDepth;
				if ( depthChange === 0 ) {
					return;
				}
				else if ( depthChange > 0 ) {
					depth = maxChildDepth + depthChange;
					if ( depth > menuMaxDepth ) {
						newDepth = depth;
					}
				}
				else if ( depthChange < 0 && maxChildDepth == menuMaxDepth ) {
					while ( ! $( '.menu-item-depth-' + newDepth, api.menuList ).length && newDepth > 0 ) {
						newDepth--;
					}
				}
				// Update the depth class.
				body.removeClass( 'menu-max-depth-' + menuMaxDepth ).addClass( 'menu-max-depth-' + newDepth );
				menuMaxDepth = newDepth;
			}
		},
		
		registerChange: function() {
			api.menusChanged = true;
		},
		
		depthToPx: function( depth ) {
			return depth * api.options.menuItemDepthPerLevel;
		},
		
		pxToDepth: function( px ) {
			return Math.floor( px / api.options.menuItemDepthPerLevel );
		}
		
	};
	
	$( document ).ready( function() {
		wpNavMenu.init();
	} );
	
} )( jQuery );
