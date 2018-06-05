<?php
namespace creemedia\Bundle\eZcontentbirdBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use creemedia\Bundle\eZcontentBirdBundle\DependencyInjection\eZcontentbirdExtension;

class eZcontentbirdBundle extends Bundle {
	protected $name = 'eZcontentbirdBundle';

	/**
	 * {@inheritdoc}
	 */
	public function getAlias()
	{
		return 'e_zcontentbird';
	}
}
