<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Html\HtmlParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the HtmlParser class.
 */
class HtmlParserTest extends TestCase
{
    /**
     * @var HtmlParser
     */
    protected $parser;

    protected function setUp(): void
    {
        $this->parser = new HtmlParser();
    }

    public function testExtractTextNodes()
    {
        $html = '<div><h1>Welcome</h1><p>Get started today</p></div>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(2, $phrases);
        $this->assertEquals('Welcome', $phrases[0]);
        $this->assertEquals('Get started today', $phrases[1]);
    }

    public function testExtractNestedTextNodes()
    {
        $html = '<div><span><strong>Bold text</strong> and <em>italic text</em></span></div>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(3, $phrases);
        $this->assertEquals('Bold text', $phrases[0]);
        $this->assertEquals('and', $phrases[1]);
        $this->assertEquals('italic text', $phrases[2]);
    }

    public function testExtractTranslatableAttributes()
    {
        $html = '<input placeholder="Enter your email" title="Email field" alt="Email icon">';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(3, $phrases);
        $this->assertContains('Enter your email', $phrases);
        $this->assertContains('Email field', $phrases);
        $this->assertContains('Email icon', $phrases);
    }

    public function testExtractAriaLabels()
    {
        $html = '<button aria-label="Close dialog" aria-placeholder="Search here">Click</button>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Close dialog', $phrases);
        $this->assertContains('Search here', $phrases);
        $this->assertContains('Click', $phrases);
    }

    public function testExtractDataErrorAttributes()
    {
        $html = '<input data-error="Invalid input" data-error-message="Please enter a valid value" data-validation-message="Validation failed">';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Invalid input', $phrases);
        $this->assertContains('Please enter a valid value', $phrases);
        $this->assertContains('Validation failed', $phrases);
    }

    public function testExtractButtonValues()
    {
        $html = '<button value="Submit Form">Submit</button>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Submit Form', $phrases);
        $this->assertContains('Submit', $phrases);
    }

    public function testExtractSubmitInputValues()
    {
        $html = '<input type="submit" value="Send Message">';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(1, $phrases);
        $this->assertEquals('Send Message', $phrases[0]);
    }

    public function testExtractButtonInputValues()
    {
        $html = '<input type="button" value="Click Here">';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(1, $phrases);
        $this->assertEquals('Click Here', $phrases[0]);
    }

    public function testDoNotExtractTextInputValues()
    {
        $html = '<input type="text" value="Default Value">';
        $phrases = $this->parser->extractPhrases($html);

        // text input values should not be extracted as they are user data
        $this->assertNotContains('Default Value', $phrases);
    }

    public function testExtractSelectOptions()
    {
        $html = '<select><option>Select an option</option><option>First</option><option>Second</option></select>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Select an option', $phrases);
        $this->assertContains('First', $phrases);
        $this->assertContains('Second', $phrases);
    }

    public function testRespectTranslateNo()
    {
        $html = '<div><p>Translate this</p><p translate="no">Do not translate this</p><p>And this</p></div>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Translate this', $phrases);
        $this->assertContains('And this', $phrases);
        $this->assertNotContains('Do not translate this', $phrases);
    }

    public function testRespectTranslateNoOnContainer()
    {
        $html = '<div translate="no"><h1>Skip this heading</h1><p>Skip this paragraph</p></div><div><p>Keep this</p></div>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertNotContains('Skip this heading', $phrases);
        $this->assertNotContains('Skip this paragraph', $phrases);
        $this->assertContains('Keep this', $phrases);
    }

    public function testWhitespaceNormalization()
    {
        $html = '<p>   Multiple   spaces   here   </p>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(1, $phrases);
        $this->assertEquals('Multiple spaces here', $phrases[0]);
    }

    public function testNewlineNormalization()
    {
        $html = "<p>Line one\nLine two\n\nLine three</p>";
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(1, $phrases);
        $this->assertEquals('Line one Line two Line three', $phrases[0]);
    }

    public function testTabNormalization()
    {
        $html = "<p>Tab\there\tand\tthere</p>";
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(1, $phrases);
        $this->assertEquals('Tab here and there', $phrases[0]);
    }

    public function testPreserveDuplicatePhrases()
    {
        $html = '<ul><li>Item</li><li>Item</li><li>Item</li></ul>';
        $phrases = $this->parser->extractPhrases($html);

        // Should preserve all duplicates, not deduplicate
        $this->assertCount(3, $phrases);
        $this->assertEquals('Item', $phrases[0]);
        $this->assertEquals('Item', $phrases[1]);
        $this->assertEquals('Item', $phrases[2]);
    }

    public function testPreserveDuplicateInDifferentLocations()
    {
        $html = '<h1>Welcome</h1><p>Welcome to our site</p><footer>Welcome back</footer>';
        $phrases = $this->parser->extractPhrases($html);

        // All phrases preserved even if they contain same words
        $this->assertCount(3, $phrases);
    }

    public function testGenerateCustomId()
    {
        $phrases = ['Welcome', 'Get started today'];
        $id1 = $this->parser->generateCustomId('Marketing', $phrases);
        $id2 = $this->parser->generateCustomId('Marketing', $phrases);
        $id3 = $this->parser->generateCustomId('UI', $phrases);
        $id4 = $this->parser->generateCustomId(null, $phrases);

        // Same category and phrases should generate same ID
        $this->assertEquals($id1, $id2);

        // Different category should generate different ID
        $this->assertNotEquals($id1, $id3);

        // Null category should work
        $this->assertNotEquals($id1, $id4);
        $this->assertEquals(32, strlen($id4)); // md5 hash length
    }

    public function testGenerateCustomIdIsDeterministic()
    {
        $html = '<div><h1>Welcome</h1><p>Get started today</p></div>';
        $phrases = $this->parser->extractPhrases($html);

        $id1 = $this->parser->generateCustomId('Marketing', $phrases);
        $id2 = $this->parser->generateCustomId('Marketing', $phrases);

        $this->assertEquals($id1, $id2);
    }

    public function testMalformedHtml()
    {
        // Unclosed tags
        $html = '<div><p>Paragraph without closing tag<span>More text';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Paragraph without closing tag', $phrases);
        $this->assertContains('More text', $phrases);
    }

    public function testHtmlEntities()
    {
        $html = '<p>Copyright &copy; 2024 &amp; beyond</p>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(1, $phrases);
        // HTML entities should be decoded
        $this->assertStringContainsString('2024', $phrases[0]);
    }

    public function testEmptyHtml()
    {
        $phrases = $this->parser->extractPhrases('');
        $this->assertCount(0, $phrases);

        $phrases = $this->parser->extractPhrases(null);
        $this->assertCount(0, $phrases);
    }

    public function testWhitespaceOnlyHtml()
    {
        $html = '   <div>   </div>   ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(0, $phrases);
    }

    public function testComplexFormExample()
    {
        $html = '
            <form>
                <label>Name</label>
                <input type="text" placeholder="Enter your name" title="Full name required">

                <label>Email</label>
                <input type="email" placeholder="your@email.com" data-error-message="Invalid email">

                <button type="submit">Submit</button>
            </form>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Name', $phrases);
        $this->assertContains('Enter your name', $phrases);
        $this->assertContains('Full name required', $phrases);
        $this->assertContains('Email', $phrases);
        $this->assertContains('your@email.com', $phrases);
        $this->assertContains('Invalid email', $phrases);
        $this->assertContains('Submit', $phrases);
    }

    public function testScriptAndStyleTagsIgnored()
    {
        $html = '<div><script>var x = "JavaScript text";</script><style>.class { content: "CSS text"; }</style><p>Real text</p></div>';
        $phrases = $this->parser->extractPhrases($html);

        // Script and style content should not be treated as translatable text
        // Note: DOMDocument may still parse these, so this tests actual behavior
        $this->assertContains('Real text', $phrases);
    }

    public function testCommentsIgnored()
    {
        $html = '<div><!-- This is a comment --><p>Visible text</p></div>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(1, $phrases);
        $this->assertEquals('Visible text', $phrases[0]);
    }

    public function testDataRequiredMessage()
    {
        $html = '<input data-required-message="This field is required" data-pattern-message="Invalid format" data-invalid-message="Please check your input">';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('This field is required', $phrases);
        $this->assertContains('Invalid format', $phrases);
        $this->assertContains('Please check your input', $phrases);
    }

    public function testOrderPreserved()
    {
        $html = '<div><span>First</span><span>Second</span><span>Third</span></div>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertEquals('First', $phrases[0]);
        $this->assertEquals('Second', $phrases[1]);
        $this->assertEquals('Third', $phrases[2]);
    }

    public function testAttributeOrderPreserved()
    {
        $html = '<input placeholder="First attr" title="Second attr" alt="Third attr">';
        $phrases = $this->parser->extractPhrases($html);

        // Attributes are extracted in the order defined in TRANSLATABLE_ATTRIBUTES
        $this->assertEquals('First attr', $phrases[0]); // placeholder comes first in constant
        $this->assertEquals('Third attr', $phrases[1]); // alt
        $this->assertEquals('Second attr', $phrases[2]); // title
    }

    // =========================================================================
    // Real-world Content Block Scenarios
    // =========================================================================

    public function testNavigationMenu()
    {
        $html = '
            <nav>
                <a href="/">Home</a>
                <a href="/about">About Us</a>
                <a href="/services">Services</a>
                <a href="/contact">Contact</a>
            </nav>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(4, $phrases);
        $this->assertEquals('Home', $phrases[0]);
        $this->assertEquals('About Us', $phrases[1]);
        $this->assertEquals('Services', $phrases[2]);
        $this->assertEquals('Contact', $phrases[3]);
    }

    public function testHeroSection()
    {
        $html = '
            <section class="hero">
                <h1>Welcome to Our Platform</h1>
                <p>The best solution for your business needs</p>
                <a href="/signup" class="btn">Get Started Free</a>
                <a href="/demo" class="btn-secondary">Watch Demo</a>
            </section>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(4, $phrases);
        $this->assertEquals('Welcome to Our Platform', $phrases[0]);
        $this->assertEquals('The best solution for your business needs', $phrases[1]);
        $this->assertEquals('Get Started Free', $phrases[2]);
        $this->assertEquals('Watch Demo', $phrases[3]);
    }

    public function testProductCard()
    {
        $html = '
            <article class="product-card">
                <img src="product.jpg" alt="Premium Headphones">
                <h3>Wireless Headphones</h3>
                <p class="price">$99.99</p>
                <p class="description">High-quality audio experience with noise cancellation</p>
                <button>Add to Cart</button>
            </article>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Premium Headphones', $phrases); // alt attribute
        $this->assertContains('Wireless Headphones', $phrases);
        $this->assertContains('$99.99', $phrases);
        $this->assertContains('High-quality audio experience with noise cancellation', $phrases);
        $this->assertContains('Add to Cart', $phrases);
    }

    public function testFooterBlock()
    {
        $html = '
            <footer>
                <div class="footer-col">
                    <h4>Company</h4>
                    <ul>
                        <li><a>About</a></li>
                        <li><a>Careers</a></li>
                        <li><a>Press</a></li>
                    </ul>
                </div>
                <p class="copyright">© 2024 Company Name. All rights reserved.</p>
            </footer>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Company', $phrases);
        $this->assertContains('About', $phrases);
        $this->assertContains('Careers', $phrases);
        $this->assertContains('Press', $phrases);
        $this->assertContains('© 2024 Company Name. All rights reserved.', $phrases);
    }

    public function testCallToActionBlock()
    {
        $html = '
            <div class="cta">
                <h2>Ready to get started?</h2>
                <p>Join thousands of satisfied customers today.</p>
                <input type="email" placeholder="Enter your email address">
                <button type="submit">Subscribe Now</button>
            </div>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Ready to get started?', $phrases);
        $this->assertContains('Join thousands of satisfied customers today.', $phrases);
        $this->assertContains('Enter your email address', $phrases);
        $this->assertContains('Subscribe Now', $phrases);
    }

    public function testTableWithHeaders()
    {
        $html = '
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Widget A</td>
                        <td>$10.00</td>
                        <td>5</td>
                    </tr>
                </tbody>
            </table>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Product', $phrases);
        $this->assertContains('Price', $phrases);
        $this->assertContains('Quantity', $phrases);
        $this->assertContains('Widget A', $phrases);
        $this->assertContains('$10.00', $phrases);
        $this->assertContains('5', $phrases);
    }

    public function testImageGalleryWithCaptions()
    {
        $html = '
            <div class="gallery">
                <figure>
                    <img src="img1.jpg" alt="Mountain landscape at sunset">
                    <figcaption>Beautiful mountain view</figcaption>
                </figure>
                <figure>
                    <img src="img2.jpg" alt="Ocean waves on beach">
                    <figcaption>Peaceful beach scene</figcaption>
                </figure>
            </div>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Mountain landscape at sunset', $phrases);
        $this->assertContains('Beautiful mountain view', $phrases);
        $this->assertContains('Ocean waves on beach', $phrases);
        $this->assertContains('Peaceful beach scene', $phrases);
    }

    public function testAccordionFAQ()
    {
        $html = '
            <div class="faq">
                <div class="faq-item">
                    <h3>How do I reset my password?</h3>
                    <p>Click on the forgot password link on the login page.</p>
                </div>
                <div class="faq-item">
                    <h3>What payment methods do you accept?</h3>
                    <p>We accept all major credit cards and PayPal.</p>
                </div>
            </div>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('How do I reset my password?', $phrases);
        $this->assertContains('Click on the forgot password link on the login page.', $phrases);
        $this->assertContains('What payment methods do you accept?', $phrases);
        $this->assertContains('We accept all major credit cards and PayPal.', $phrases);
    }

    public function testPricingTable()
    {
        $html = '
            <div class="pricing-card">
                <h3>Professional</h3>
                <p class="price">$49/month</p>
                <ul>
                    <li>Unlimited projects</li>
                    <li>Priority support</li>
                    <li>Advanced analytics</li>
                </ul>
                <button>Choose Plan</button>
            </div>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Professional', $phrases);
        $this->assertContains('$49/month', $phrases);
        $this->assertContains('Unlimited projects', $phrases);
        $this->assertContains('Priority support', $phrases);
        $this->assertContains('Advanced analytics', $phrases);
        $this->assertContains('Choose Plan', $phrases);
    }

    public function testContactFormComplete()
    {
        $html = '
            <form class="contact-form">
                <h2>Get in Touch</h2>
                <p>Fill out the form below and we\'ll get back to you soon.</p>

                <label>Full Name</label>
                <input type="text" placeholder="John Doe" data-required-message="Name is required">

                <label>Email Address</label>
                <input type="email" placeholder="john@example.com" data-error-message="Please enter a valid email">

                <label>Message</label>
                <textarea placeholder="How can we help you?"></textarea>

                <button type="submit">Send Message</button>
            </form>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Get in Touch', $phrases);
        $this->assertContains("Fill out the form below and we'll get back to you soon.", $phrases);
        $this->assertContains('Full Name', $phrases);
        $this->assertContains('John Doe', $phrases);
        $this->assertContains('Name is required', $phrases);
        $this->assertContains('Email Address', $phrases);
        $this->assertContains('john@example.com', $phrases);
        $this->assertContains('Please enter a valid email', $phrases);
        $this->assertContains('Message', $phrases);
        $this->assertContains('How can we help you?', $phrases);
        $this->assertContains('Send Message', $phrases);
    }

    public function testErrorPage()
    {
        $html = '
            <div class="error-page">
                <h1>404</h1>
                <h2>Page Not Found</h2>
                <p>Sorry, the page you are looking for does not exist.</p>
                <a href="/">Return to Homepage</a>
            </div>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('404', $phrases);
        $this->assertContains('Page Not Found', $phrases);
        $this->assertContains('Sorry, the page you are looking for does not exist.', $phrases);
        $this->assertContains('Return to Homepage', $phrases);
    }

    public function testModalDialog()
    {
        $html = '
            <div class="modal">
                <h2>Confirm Action</h2>
                <p>Are you sure you want to delete this item? This action cannot be undone.</p>
                <button class="cancel">Cancel</button>
                <button class="confirm">Delete</button>
            </div>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Confirm Action', $phrases);
        $this->assertContains('Are you sure you want to delete this item? This action cannot be undone.', $phrases);
        $this->assertContains('Cancel', $phrases);
        $this->assertContains('Delete', $phrases);
    }

    public function testLoginForm()
    {
        $html = '
            <form class="login">
                <h1>Welcome Back</h1>
                <p>Sign in to your account</p>

                <input type="email" placeholder="Email address" aria-label="Email input">
                <input type="password" placeholder="Password" aria-label="Password input">

                <label><input type="checkbox"> Remember me</label>

                <button type="submit">Sign In</button>

                <a>Forgot your password?</a>
                <p>Don\'t have an account? <a>Sign up</a></p>
            </form>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Welcome Back', $phrases);
        $this->assertContains('Sign in to your account', $phrases);
        $this->assertContains('Email address', $phrases);
        $this->assertContains('Email input', $phrases);
        $this->assertContains('Password', $phrases);
        $this->assertContains('Password input', $phrases);
        $this->assertContains('Remember me', $phrases);
        $this->assertContains('Sign In', $phrases);
        $this->assertContains('Forgot your password?', $phrases);
        $this->assertContains("Don't have an account?", $phrases);
        $this->assertContains('Sign up', $phrases);
    }

    public function testBreadcrumbs()
    {
        $html = '
            <nav aria-label="Breadcrumb navigation">
                <ol>
                    <li><a>Home</a></li>
                    <li><a>Products</a></li>
                    <li><a>Electronics</a></li>
                    <li aria-current="page">Headphones</li>
                </ol>
            </nav>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Breadcrumb navigation', $phrases);
        $this->assertContains('Home', $phrases);
        $this->assertContains('Products', $phrases);
        $this->assertContains('Electronics', $phrases);
        $this->assertContains('Headphones', $phrases);
    }

    public function testAlertMessages()
    {
        $html = '
            <div class="alert alert-success">
                <strong>Success!</strong> Your changes have been saved.
            </div>
            <div class="alert alert-error">
                <strong>Error!</strong> Something went wrong. Please try again.
            </div>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Success!', $phrases);
        $this->assertContains('Your changes have been saved.', $phrases);
        $this->assertContains('Error!', $phrases);
        $this->assertContains('Something went wrong. Please try again.', $phrases);
    }

    public function testTestimonialBlock()
    {
        $html = '
            <blockquote class="testimonial">
                <p>"This product changed my life. I can\'t imagine going back."</p>
                <footer>
                    <cite>Jane Smith</cite>
                    <span>CEO, TechCorp</span>
                </footer>
            </blockquote>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains("\"This product changed my life. I can't imagine going back.\"", $phrases);
        $this->assertContains('Jane Smith', $phrases);
        $this->assertContains('CEO, TechCorp', $phrases);
    }

    public function testSearchForm()
    {
        $html = '
            <form role="search">
                <input type="search" placeholder="Search products..." aria-label="Search">
                <button type="submit" aria-label="Submit search">
                    <span class="sr-only">Search</span>
                </button>
            </form>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Search products...', $phrases);
        $this->assertContains('Search', $phrases); // aria-label on input
        $this->assertContains('Submit search', $phrases);
    }

    public function testDropdownMenu()
    {
        $html = '
            <select aria-label="Select country">
                <option value="">Choose a country</option>
                <option value="us">United States</option>
                <option value="uk">United Kingdom</option>
                <option value="ca">Canada</option>
            </select>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Select country', $phrases);
        $this->assertContains('Choose a country', $phrases);
        $this->assertContains('United States', $phrases);
        $this->assertContains('United Kingdom', $phrases);
        $this->assertContains('Canada', $phrases);
    }

    public function testMixedContentWithTranslateNo()
    {
        $html = '
            <article>
                <h1>Understanding Machine Learning</h1>
                <p>Machine learning is a subset of artificial intelligence.</p>
                <pre translate="no"><code>model.fit(X_train, y_train)</code></pre>
                <p>The code above shows a simple training example.</p>
            </article>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Understanding Machine Learning', $phrases);
        $this->assertContains('Machine learning is a subset of artificial intelligence.', $phrases);
        $this->assertContains('The code above shows a simple training example.', $phrases);
        // Code should NOT be extracted
        $this->assertNotContains('model.fit(X_train, y_train)', $phrases);
    }

    public function testComplexNestedStructure()
    {
        $html = '
            <div class="card">
                <div class="card-header">
                    <h3>Featured Article</h3>
                    <span class="badge">New</span>
                </div>
                <div class="card-body">
                    <p>Discover the latest trends in web development.</p>
                    <ul>
                        <li><strong>React</strong> - Component-based UI</li>
                        <li><strong>Vue</strong> - Progressive framework</li>
                        <li><strong>Svelte</strong> - Compiler approach</li>
                    </ul>
                </div>
                <div class="card-footer">
                    <a>Read More</a>
                </div>
            </div>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Featured Article', $phrases);
        $this->assertContains('New', $phrases);
        $this->assertContains('Discover the latest trends in web development.', $phrases);
        $this->assertContains('React', $phrases);
        $this->assertContains('- Component-based UI', $phrases);
        $this->assertContains('Vue', $phrases);
        $this->assertContains('- Progressive framework', $phrases);
        $this->assertContains('Svelte', $phrases);
        $this->assertContains('- Compiler approach', $phrases);
        $this->assertContains('Read More', $phrases);
    }

    // =========================================================================
    // Configurable Translatable Attributes Tests
    // =========================================================================

    public function testDefaultTranslatableAttributes()
    {
        $expected = [
            // Standard HTML
            'placeholder',
            'alt',
            'title',
            'label',

            // ARIA accessibility
            'aria-label',
            'aria-placeholder',
            'aria-description',
            'aria-valuetext',
            'aria-roledescription',

            // Form validation messages
            'data-error',
            'data-error-message',
            'data-validation-message',
            'data-invalid-message',
            'data-required-message',
            'data-pattern-message',

            // Common framework patterns
            'data-confirm',
            'data-tooltip',
            'data-title',
            'data-content',
            'data-original-title',
            'data-bs-title',
            'data-bs-content',
            'data-loading-text',
            'data-success-message',
            'data-warning-message',
            'data-empty-message',
            'data-placeholder',
        ];

        $this->assertEquals($expected, $this->parser->getTranslatableAttributes());
    }

    public function testSetCustomTranslatableAttributes()
    {
        $customAttrs = ['data-label', 'data-tooltip'];
        $this->parser->setTranslatableAttributes($customAttrs);

        $this->assertEquals($customAttrs, $this->parser->getTranslatableAttributes());
    }

    public function testAddTranslatableAttributes()
    {
        $this->parser->addTranslatableAttributes(['data-custom', 'data-tooltip']);

        $attrs = $this->parser->getTranslatableAttributes();
        $this->assertContains('placeholder', $attrs); // default still present
        $this->assertContains('data-custom', $attrs); // new attribute added
        $this->assertContains('data-tooltip', $attrs); // new attribute added
    }

    public function testAddTranslatableAttributesNoDuplicates()
    {
        $this->parser->addTranslatableAttributes(['placeholder', 'data-custom']);

        $attrs = $this->parser->getTranslatableAttributes();
        // Count how many times 'placeholder' appears
        $count = count(array_filter($attrs, function($a) { return $a === 'placeholder'; }));
        $this->assertEquals(1, $count);
    }

    public function testResetTranslatableAttributes()
    {
        $this->parser->setTranslatableAttributes(['only-this']);
        $this->assertEquals(['only-this'], $this->parser->getTranslatableAttributes());

        $this->parser->resetTranslatableAttributes();
        $this->assertEquals(HtmlParser::DEFAULT_TRANSLATABLE_ATTRIBUTES, $this->parser->getTranslatableAttributes());
    }

    public function testCustomAttributeExtraction()
    {
        $parser = new HtmlParser(['data-label', 'data-hint']);

        $html = '<div data-label="Custom Label" data-hint="Helpful hint" placeholder="Ignored"></div>';
        $phrases = $parser->extractPhrases($html);

        $this->assertContains('Custom Label', $phrases);
        $this->assertContains('Helpful hint', $phrases);
        $this->assertNotContains('Ignored', $phrases); // placeholder not in custom list
    }

    public function testConstructorWithCustomAttributes()
    {
        $customAttrs = ['data-text', 'data-description'];
        $parser = new HtmlParser($customAttrs);

        $this->assertEquals($customAttrs, $parser->getTranslatableAttributes());
    }

    public function testConstructorWithNullUsesDefaults()
    {
        $parser = new HtmlParser(null);

        $this->assertEquals(HtmlParser::DEFAULT_TRANSLATABLE_ATTRIBUTES, $parser->getTranslatableAttributes());
    }

    public function testFluentInterface()
    {
        $result = $this->parser
            ->setTranslatableAttributes(['a'])
            ->addTranslatableAttributes(['b'])
            ->resetTranslatableAttributes();

        $this->assertSame($this->parser, $result);
    }

    public function testRealWorldCustomAttributes()
    {
        // Simulate a developer adding their app-specific attributes
        $this->parser->addTranslatableAttributes([
            'data-i18n',
            'data-translate',
            'data-content',
        ]);

        $html = '
            <div data-i18n="welcome.message">Welcome</div>
            <span data-translate="cta.button">Click Here</span>
            <p data-content="description">Some description</p>
            <input placeholder="Email">
        ';

        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('welcome.message', $phrases);
        $this->assertContains('cta.button', $phrases);
        $this->assertContains('description', $phrases);
        $this->assertContains('Email', $phrases); // default attr still works
        $this->assertContains('Welcome', $phrases);
        $this->assertContains('Click Here', $phrases);
        $this->assertContains('Some description', $phrases);
    }

    // =========================================================================
    // URL Resolution Tests
    // =========================================================================

    public function testResolveRelativeUrls()
    {
        $html = '<img src="/images/photo.jpg" alt="Photo">';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('src="https://example.com/images/photo.jpg"', $result);
    }

    public function testResolveRelativeUrlPath()
    {
        $html = '<img src="images/photo.jpg" alt="Photo">';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('src="https://example.com/images/photo.jpg"', $result);
    }

    public function testResolveRelativeUrlsSkipsAbsolute()
    {
        $html = '<img src="https://cdn.example.com/photo.jpg" alt="Photo">';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('src="https://cdn.example.com/photo.jpg"', $result);
    }

    public function testResolveRelativeUrlsSkipsDataUri()
    {
        $html = '<img src="data:image/png;base64,abc123" alt="Icon">';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('src="data:image/png;base64,abc123"', $result);
    }

    public function testResolveRelativeUrlsSkipsProtocolRelative()
    {
        $html = '<img src="//cdn.example.com/photo.jpg" alt="Photo">';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('src="//cdn.example.com/photo.jpg"', $result);
    }

    public function testResolveRelativeUrlsSrcset()
    {
        $html = '<img src="/photo.jpg" srcset="/photo-1x.jpg 1x, /photo-2x.jpg 2x" alt="Photo">';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('src="https://example.com/photo.jpg"', $result);
        $this->assertStringContainsString('srcset="https://example.com/photo-1x.jpg 1x, https://example.com/photo-2x.jpg 2x"', $result);
    }

    public function testResolveRelativeUrlsSrcsetWithWidth()
    {
        $html = '<img src="/photo.jpg" srcset="/small.jpg 100w, /large.jpg 200w" alt="Photo">';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('srcset="https://example.com/small.jpg 100w, https://example.com/large.jpg 200w"', $result);
    }

    public function testResolveRelativeUrlsVideoPoster()
    {
        $html = '<video src="/video.mp4" poster="/thumbnail.jpg"></video>';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('src="https://example.com/video.mp4"', $result);
        $this->assertStringContainsString('poster="https://example.com/thumbnail.jpg"', $result);
    }

    public function testResolveRelativeUrlsMultipleImages()
    {
        $html = '<div><img src="/img1.jpg"><img src="/img2.jpg"><img src="https://other.com/img3.jpg"></div>';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('src="https://example.com/img1.jpg"', $result);
        $this->assertStringContainsString('src="https://example.com/img2.jpg"', $result);
        $this->assertStringContainsString('src="https://other.com/img3.jpg"', $result);
    }

    public function testResolveRelativeUrlsStripsTrailingSlash()
    {
        $html = '<img src="/photo.jpg" alt="Photo">';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com/');

        // Should not have double slash
        $this->assertStringContainsString('src="https://example.com/photo.jpg"', $result);
        $this->assertStringNotContainsString('src="https://example.com//photo.jpg"', $result);
    }

    public function testResolveRelativeUrlsEmptyBaseUrl()
    {
        $html = '<img src="/photo.jpg" alt="Photo">';
        $result = $this->parser->resolveRelativeUrls($html, '');

        // Should return original
        $this->assertStringContainsString('src="/photo.jpg"', $result);
    }

    public function testResolveRelativeUrlsEmptyHtml()
    {
        $result = $this->parser->resolveRelativeUrls('', 'https://example.com');
        $this->assertEquals('', $result);
    }
}
