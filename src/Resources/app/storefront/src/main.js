const PluginManager = window.PluginManager;

import FreshdeskFormPlugin from './freshdesk-form/freshdesk-form.plugin';

PluginManager.register('FreshdeskFormPlugin', FreshdeskFormPlugin, '.freshdesk-standard-form');
