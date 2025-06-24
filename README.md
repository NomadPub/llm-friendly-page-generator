# LLM-Friendly Page Generator

**Contributors:** Damon Noisette  
**Tags:** llm, markdown, ai-friendly content, page generator, speedycache  
**Requires at least:** 5.0  
**Tested up to:** 6.5  
**Requires PHP:** 7.2  
**Stable tag:** 1.3  
**License:** MIT 
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html   

Generates LLM-friendly Markdown (`.md`) files for all WordPress pages and supports scheduled regeneration per post type.

## Description

The **LLM-Friendly Page Generator** plugin automatically creates clean, structured Markdown versions of your WordPress site's content in compliance with the [LLM-Friendly Content Format](https://llmstxt.org/)  [[9]](https://adsby.co/llmstxt-explained/).  These `.md` files are ideal for use by AI models and large language tools.

This plugin also integrates with the SpeedCache plugin to ensure Markdown files are regenerated whenever the cache is cleared.

### Features:
- Automatically generates `.md` files for every published page.
- Fully compatible with custom post types.
- Regenerates files when SpeedCache clears the cache.
- Supports scheduled regeneration (hourly, daily, weekly, etc.) for each post type.
- Includes a central `llms.txt` file listing all available Markdown files [[4]](https://github.com/llms-txt/llms-txt). 
- Easy-to-use settings under **Tools > LLM Pages**.

For more information about how this aligns with the LLM-friendly format standard, see [llmstxt.org](https://llmstxt.org/)  [[9]](https://adsby.co/llmstxt-explained/). 

## Installation

1. Upload the `llm-friendly-page-generator` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Tools > LLM Pages** to configure regeneration schedules and manually regenerate files if needed.

Ensure that the following Composer library is installed:

- `league/html-to-markdown` — required for HTML to Markdown conversion [[6]](https://medium.com/@wetrocloud/why-markdown-is-the-best-format-for-llms). 

Run the following command in your plugin directory: `bash composer require league/html-to-markdown`

### Frequently Asked Questions
*How do I find the generated Markdown files?*
All files are stored under the wp-content/llms/ directory and mirror your site’s URL structure. You can access them directly via your domain, e.g., https://example.com/about.md.

*What is the llms.txt file used for?*
The llms.txt file lists all available Markdown files and follows the standard described at llmstxt.org , making it easy for AI crawlers to discover and index your LLM-ready content 
(https://github.com/llms-txt/llms-txt ).

*Can I schedule different update intervals for posts vs. pages?*
Yes! Go to Tools > LLM Pages and set individual schedules for each post type, including custom post types.
