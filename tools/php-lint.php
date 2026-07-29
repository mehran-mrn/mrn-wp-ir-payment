<?php
/**
 * Lint every distributable PHP file.
 *
 * @package MRN\IranPayment
 */

$root     = dirname( __DIR__ );
$iterator = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		static function ( SplFileInfo $file ) {
			return 'vendor' !== $file->getFilename() && '.git' !== $file->getFilename();
		}
	)
);
$failed = false;
foreach ( $iterator as $file ) {
	if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
		$command = escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $file->getPathname() );
		exec( $command, $output, $code );
		if ( 0 !== $code ) {
			fwrite( STDERR, implode( PHP_EOL, $output ) . PHP_EOL );
			$failed = true;
		}
		$output = array();
	}
}
exit( $failed ? 1 : 0 );
