<?php

namespace WP_Parser;

/**
 * Get all PHP files from a directory recursively.
 *
 * @param string $directory Directory to scan.
 * @return array|\WP_Error Array of file paths or WP_Error on failure.
 */
function get_wp_files( $directory ) {
	$iterableFiles = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator( $directory )
	);
	$files         = array();

	try {
		foreach ( $iterableFiles as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			$files[] = $file->getPathname();
		}
	} catch ( \UnexpectedValueException $exc ) {
		return new \WP_Error(
			'unexpected_value_exception',
			sprintf( 'Directory [%s] contained a directory we can not recurse into', $directory )
		);
	}

	return $files;
}

/**
 * Parse PHP files using the modernized parser.
 *
 * @param array  $files Array of file paths to parse.
 * @param string $root  Root directory path.
 * @return array Parsed data in legacy format for compatibility.
 */
function parse_files( $files, $root ) {
	$output = array();

	foreach ( $files as $filename ) {
		$content = file_get_contents( $filename );
		if ( false === $content ) {
			continue;
		}

		$file_reflector = new File_Reflector( $filename, $content );
		$parsed_data = $file_reflector->parse();

		$path = ltrim( substr( $filename, strlen( $root ) ), DIRECTORY_SEPARATOR );

		// TODO proper exporter
		$out = array(
			'file' => export_docblock_from_data( $parsed_data['file_docblock'] ),
			'path' => str_replace( DIRECTORY_SEPARATOR, '/', $path ),
			'root' => $root,
		);

		// Add file-level uses (hooks, functions, methods)
		if ( ! empty( $parsed_data['uses'] ) ) {
			$uses = export_uses( $parsed_data['uses'] );
			if ( $uses ) {
				$out['uses'] = $uses;
			}
		}

		// Convert hooks to legacy format
		if ( ! empty( $parsed_data['uses']['hooks'] ) ) {
			$out['hooks'] = export_hooks( $parsed_data['uses']['hooks'] );
		}

		// Convert functions to legacy format
		if ( ! empty( $parsed_data['functions'] ) ) {
			$out['functions'] = array();
			foreach ( $parsed_data['functions'] as $function ) {
				$func = array(
					'name' => $function['name'],
					'namespace' => $function['namespace'] ?? 'global',
					'aliases' => array(),
					'line' => $function['line'],
					'end_line' => $function['end_line'],
					'arguments' => export_arguments( $function['parameters'] ?? array() ),
					'doc' => export_docblock_from_data( $function['docblock'] ),
					'hooks' => array(),
				);

				// Add function-level uses
				if ( ! empty( $function['uses'] ) ) {
					$func['uses'] = export_uses( $function['uses'] );

					// Extract hooks from function uses
					if ( ! empty( $function['uses']['hooks'] ) ) {
						$func['hooks'] = export_hooks( $function['uses']['hooks'] );
					}
				}

				$out['functions'][] = $func;
			}
		}

		// Convert classes to legacy format
		if ( ! empty( $parsed_data['classes'] ) ) {
			$out['classes'] = array();
			foreach ( $parsed_data['classes'] as $class ) {
				$class_data = array(
					'name' => $class['name'],
					'namespace' => $class['namespace'] ?? 'global',
					'line' => $class['line'],
					'end_line' => $class['end_line'],
					'doc' => export_docblock_from_data( $class['docblock'] ),
					'uses' => array(),
					'methods' => array(),
					'properties' => array(),
				);

				// Convert methods
				if ( ! empty( $class['methods'] ) ) {
					foreach ( $class['methods'] as $method ) {
						$method_data = array(
							'name' => $method['name'],
							'line' => $method['line'],
							'end_line' => $method['end_line'],
							'arguments' => export_arguments( $method['parameters'] ?? array() ),
							'doc' => export_docblock_from_data( $method['docblock'] ),
							'visibility' => $method['visibility'],
							'final' => false, // Would need to be added to parser
							'static' => $method['static'],
							'abstract' => false, // Would need to be added to parser
							'hooks' => array(),
						);

						// Add method-level uses
						if ( ! empty( $method['uses'] ) ) {
							$method_data['uses'] = export_uses( $method['uses'] );

							// Extract hooks from method uses
							if ( ! empty( $method['uses']['hooks'] ) ) {
								$method_data['hooks'] = export_hooks( $method['uses']['hooks'] );
							}
						}

						$class_data['methods'][] = $method_data;
					}
				}

				// Convert properties
				if ( ! empty( $class['properties'] ) ) {
					foreach ( $class['properties'] as $property ) {
						$property_data = array(
							'name' => $property['name'],
							'line' => $property['line'],
							'end_line' => $property['end_line'],
							'doc' => export_docblock_from_data( $property['docblock'] ),
							'visibility' => $property['visibility'],
							'static' => $property['static'],
							'default' => $property['default'],
						);

						$class_data['properties'][] = $property_data;
					}
				}

				$out['classes'][] = $class_data;
			}
		}

		$output[] = $out;
	}

	return $output;
}

/**
 * Export uses data to legacy format.
 *
 * @param array $uses Uses data from modern parser.
 * @return array Legacy format uses.
 */
function export_uses( $uses ) {
	$exported = array();

	if ( ! empty( $uses['functions'] ) ) {
		$exported['functions'] = array();
		foreach ( $uses['functions'] as $function ) {
			$exported['functions'][] = export_function_call( $function );
		}
	}

	if ( ! empty( $uses['methods'] ) ) {
		$exported['methods'] = array();
		foreach ( $uses['methods'] as $method ) {
			$exported['methods'][] = export_method_call( $method );
		}
	}

	if ( ! empty( $uses['hooks'] ) ) {
		$exported['hooks'] = array();
		foreach ( $uses['hooks'] as $hook ) {
			$exported['hooks'][] = export_hook( $hook );
		}
	}

	return $exported;
}

/**
 * Export hooks to legacy format.
 *
 * @param array $hooks Hooks data.
 * @return array Legacy format hooks.
 */
function export_hooks( $hooks ) {
	$exported = array();

	foreach ( $hooks as $hook ) {
		$exported[] = export_hook( $hook );
	}

	return $exported;
}

/**
 * Export a single hook to legacy format.
 *
 * @param Hook_Reflector $hook Hook reflector instance.
 * @return array Legacy format hook data.
 */
function export_hook( $hook ) {
	$doc_comment = $hook->getDocComment();
	$doc = array(
		'description' => '',
		'long_description' => '',
		'tags' => array(),
	);

	if ( $doc_comment ) {
		// Parse basic doc comment for hooks
		$lines = explode( "\n", trim( str_replace( array( '/**', '*/', '*' ), '', $doc_comment ) ) );
		$description_lines = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( $line && ! str_starts_with( $line, '@' ) ) {
				$description_lines[] = $line;
			}
		}
		if ( ! empty( $description_lines ) ) {
			$doc['description'] = implode( ' ', $description_lines );
		}
	}

	return array(
		'name' => $hook->getName(),
		'line' => $hook->getLine(),
		'end_line' => $hook->getEndLine(),
		'type' => $hook->getType(),
		'arguments' => $hook->getArguments(),
		'doc' => $doc,
	);
}

/**
 * Export function call to legacy format.
 *
 * @param Function_Call_Reflector $function Function call reflector.
 * @return array Legacy format function call data.
 */
function export_function_call( $function ) {
	return array(
		'name' => $function->getName(),
		'line' => $function->getLine(),
		'end_line' => $function->getEndLine(),
	);
}

/**
 * Export method call to legacy format.
 *
 * @param Method_Call_Reflector|Static_Method_Call_Reflector $method Method call reflector.
 * @return array Legacy format method call data.
 */
function export_method_call( $method ) {
	$data = array(
		'name' => $method->getName(),
		'line' => $method->getLine(),
		'end_line' => $method->getEndLine(),
		'static' => $method->isStatic(),
	);

	if ( method_exists( $method, 'getClass' ) ) {
		$data['class'] = $method->getClass();
	}

	return $data;
}

/**
 * Export arguments to legacy format.
 *
 * @param array $parameters Parameters data from modern parser.
 * @return array Legacy format arguments.
 */
function export_arguments( $parameters ) {
	$arguments = array();

	foreach ( $parameters as $param ) {
		$arguments[] = array(
			'name' => '$' . $param['name'], // Add $ prefix for variable names
			'type' => $param['type'] ?? '',  // Use empty string instead of null
			'default' => $param['default'],
			// Note: 'line' field not included in legacy format
		);
	}

	return $arguments;
}

/**
 * Export docblock from parsed data to legacy format.
 *
 * @param array|null $docblock_data Parsed docblock data.
 * @return array Legacy format docblock.
 */
function export_docblock_from_data( $docblock_data ) {
	if ( ! $docblock_data ) {
		return array(
			'description' => '',
			'long_description' => '',
			'tags' => array(),
		);
	}

	$tags = array();
	if ( ! empty( $docblock_data['tags'] ) ) {
		foreach ( $docblock_data['tags'] as $tag_name => $tag_values ) {
			foreach ( $tag_values as $value ) {
				$tag_data = array(
					'name' => $tag_name,
					'content' => $value,
				);

				// Parse @param and @return tags to extract types and variables
				if ( in_array( $tag_name, array( 'param', 'var', 'type', 'return', 'since', 'see' ), true ) ) {
					$parsed_tag = export_parse_tag( $tag_name, $value );
					$tag_data = array_merge( $tag_data, $parsed_tag );
				}

				$tags[] = $tag_data;
			}
		}
	}

	// Format descriptions according to legacy expectations:
	// - description (summary) should be plain text
	// - long_description should be wrapped in HTML paragraphs with linebreaks removed. TODO: Convert with markdown, ` and >.
	$description = $docblock_data['summary'] ?? '';
	$long_description = $docblock_data['description'] ?? '';

	if ( $long_description ) {
		$long_description = apply_markup( $long_description );
	}

	return array(
		'description' => $description,
		'long_description' => $long_description,
		'tags' => $tags,
	);
}

/**
 * Legacy function for backward compatibility.
 * Export docblock from old-style reflector.
 *
 * @param object $reflector Legacy reflector object.
 * @return array Docblock data.
 */
function export_docblock( $reflector ) {
	// This function exists for backward compatibility
	// but shouldn't be called with our new architecture
	return array(
		'description' => '',
		'long_description' => '',
		'tags' => array(),
	);
}

/**
 * Parse a docblock tag into the legacy format.
 *
 * @param string $tag_name The tag name (param, return, etc).
 * @param string $value The tag value string.
 * @return array Additional tag fields for the legacy format.
 */
function export_parse_tag( $tag_name, $value ) {
	$result = array();

	$regex_type = '([^(\s)]+|[(]\s*[^)]+\s*[)])'; // ( int | string ), int, etc
	$type_parser = static function( $types ) {
		$types = trim( $types, '() ' );
		$types = explode( '|', $types );
		$types = array_map( 'trim', $types );

		return $types;
	};

	if ( 'param' === $tag_name || 'var' === $tag_name || 'type' === $tag_name ) {
		// Parse @param type $variable description

		if ( preg_match( '/^(?<types>' . $regex_type . ')\s+(?<variable>\$\w+)\s+(?<content>.*)$/s', $value, $matches ) ) {
			$result['types'] = $type_parser( $matches['types'] );
			$result['variable'] = $matches['variable'];
			$result['content'] = $matches['content'];
		} elseif ( preg_match( '/^(?<types>' . $regex_type . ')\s+(?<content>.*)$/s', $value, $matches ) ) {
			$result['types'] = $type_parser( $matches['types'] );
			$result['variable'] = '';
			$result['content'] = $matches['content'];
		} else {
			$result['types'] = $type_parser( $value );
			$result['variable'] = '';
			$result['content'] = '';
		}
	} elseif ( 'return' === $tag_name ) {
		// Parse @return type description
		if ( preg_match( '/^(?<types>' . $regex_type . ')\s+(?<content>.*)$/s', $value, $matches ) ) {
			$result['types'] = $type_parser( $matches['types'] );
			$result['content'] = $matches['content'];
		} else {
			$result['types'] = $type_parser( $value );
			$result['content'] = '';
		}
	} elseif ( 'since' === $tag_name ) {
		// @since has a description?
		if ( preg_match( '/^([0-9.]+)\s+(.*)$/', $value, $matches ) ) {
			$result['content'] = $matches[1];
			$result['description'] = $matches[2];
		} else {
		// Unsure, some files seem to trigger this, others don't.
		//	$result['description'] = $value;
		}
	} elseif ( 'see' === $tag_name ) {
		// @see can have a URL or reference
		if ( preg_match( '#^(https?://\S+)\s*(.*)$#i', $value, $matches ) ) {
			$result = array(
				'refers' => $matches[1],
				'content' => $matches[2],
			);
		} elseif ( preg_match( '/^(\S+)\s*(.*)$/', $value, $matches ) ) {
			$result = array(
				'refers' => $matches[1],
				'content' => $matches[2],
			);
		} else {
			$result = array(
				'content' => '',
				'refers' => $value,
			);
		}
	}

	foreach ( array( 'content', 'description' ) as $field ) {
		if ( isset( $result[ $field ] ) ) {
			$result[ $field ] = apply_markup( $result[ $field ], false );
		}
	}

	return $result;
}

/**
 * Apply simple markup to a string for legacy long_description.
 *
 * Marks up `code` and >quoted text along with paragraphs.
 *
 * TODO: This should be the markdown parser, but AI coded this up pretty quickly.
 *
 * @param string $string Input string.
 * @return string Marked up string.
 */
function apply_markup( $string, $paragraphs = true ) {
	if ( ! $string ) {
		return '';
	}

	// HTML Entities
	$string = htmlspecialchars( $string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );

	// Convert `code` to <code>code</code>
	$string = preg_replace_callback(
		'/`([^`]+?)`/',
		static function( $matches ) {
			$code_content = html_entity_decode( $matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
			return '<code>' . $code_content . '</code>';
		},
		$string
	);

	// Italics
	$string = preg_replace( '/\b_([^_]+?)_\b/', '<em>$1</em>', $string );

	// Bold
	$string = preg_replace( '/\b\*([^\*]+?)\*\b/', '<strong>$1</strong>', $string );

	// Convert >quoted text to <blockquote>quoted text</blockquote>
	$string = preg_replace( '/^(>|&gt;)\s*(.+)/m', '<blockquote>$2</blockquote>', $string );
	$string = preg_replace( '#</blockquote>(\s*)<blockquote>#', '$1', $string ); // Merge adjacent blockquotes

	// Headings
	$string = preg_replace( '/^######\s*(.+)$/m', '<h6>$1</h6>', $string );
	$string = preg_replace( '/^#####\s*(.+)$/m', '<h5>$1</h5>', $string );
	$string = preg_replace( '/^####\s*(.+)$/m', '<h4>$1</h4>', $string );
	$string = preg_replace( '/^###\s*(.+)$/m', '<h3>$1</h3>', $string );
	$string = preg_replace( '/^##\s*(.+)$/m', '<h2>$1</h2>', $string );
	$string = preg_replace( '/^#\s*(.+)$/m', '<h1>$1</h1>', $string );

	// Lists.
	$string = preg_replace( '/^\s*[\*\-\+]\s+(.+)$/m', '<li>$1</li>', $string );
	$string = preg_replace( '/(<li>.*<\/li>)/sU', '<ul>$1</ul>', $string );
	$string = preg_replace( '/<\/ul>\s*<ul>/', '', $string ); // Merge adjacent lists

	// Convert blocks to paragraps.
	$string = str_replace( "\n\n", "\nPARAGRAPHBREAKHERE", $string );
	$string = str_replace( "\n", ' ', $string );
	$string = str_replace( 'PARAGRAPHBREAKHERE', "\n", $string );

	// Wrap paragraphs in <p> tags
	if ( $paragraphs ) {
		$paragraphs = explode( "\n", trim( $string ) );
		$paragraphs = array_map( 'trim', $paragraphs );
		$paragraphs = array_filter( $paragraphs ); // Remove empty paragraphs
		$paragraphs = array_map( function( $p ) {
			if ( str_starts_with( $p, '<blockquote>' ) && str_ends_with( $p, '</blockquote>' ) ) {
				// Paragraph goes inside the block quote. Probably an error.
				return str_replace( array( '<blockquote>', '</blockquote>' ), array( '<blockquote><p>', '</p></blockquote>' ), $p );
			}
			return '<p>' . $p . '</p>';
		}, $paragraphs );
		$string = implode( ' ', $paragraphs );
	}

	return $string;
}