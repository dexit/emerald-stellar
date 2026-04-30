/**
 * SEO Audit for Elementor - Using Chrome's Built-in AI (Gemini Nano)
 * Based on AI Auto Content Generator for Elementor implementation
 */

if (typeof jQuery != "undefined") {
  (function ($) {
    "use strict";

    class SEOAuditHandler {
      constructor(controlView) {
        this.controls = $(controlView)
          .get(0)
          .$el.parentsUntil(".elementor-controls-stack");
        this.editor = this.controls.find(".elementor-wp-editor");
        this.currentSession = null;
        this.downloadInProgress = false;
        this.downloadCompleted = false;
        this.summarizerProgress = 0;
        this.languageProgress = 0;
      }

      /**
       * Get content from Elementor editor
       */
      async getContent() {
        const editorId = this.editor.attr("id");
        if (window.parent.tinyMCE && editorId) {
          const editor = window.parent.tinyMCE.get(editorId);
          if (editor) {
            const content = editor.getContent();
            return content.replace(/<\/?[^>]+(>|$)/g, ""); // Strip HTML tags
          }
        }
        return "";
      }

      /**
       * Check if browser is Chrome
       */
      checkBrowser() {
        if (!window.hasOwnProperty("chrome") || !navigator.userAgent.includes("Chrome") || navigator.userAgent.includes("Edg")) {
          Swal.fire({
            title: "Chrome Browser Required",
            html: `<div class="seo-audit-error">
              <p>This feature requires Google Chrome browser with built-in AI support.</p>
            </div>`,
            showCloseButton: true,
            showConfirmButton: false,
            width: 400,
          });
          return false;
        }
        return true;
      }

      /**
       * Check Chrome version (requires 128+)
       */
      checkChromeVersion(minVersion = 128) {
        const raw = navigator.userAgent.match(/Chrom(e|ium)\/([0-9]+)\./);
        const version = raw ? parseInt(raw[2], 10) : null;

        if (!version || version < minVersion) {
          Swal.fire({
            title: "Chrome Update Required",
            html: `<div class="seo-audit-error">
              <p>Please update Chrome to version ${minVersion}+ to use AI-powered SEO analysis.</p>
            </div>`,
            showCloseButton: true,
            showConfirmButton: false,
            width: 400,
          });
          return false;
        }
        return true;
      }

      /**
       * Check if running in secure context (HTTPS)
       */
      checkSecureContext() {
        if (!window.isSecureContext) {
          Swal.fire({
            title: "⚠ Secure Connection Required",
            html: `<div class="seo-audit-error">
                <p>To use Chrome's built-in AI, enable this flag:</p>
                <ol>
                    <li>Go to: <b>chrome://flags/#unsafely-treat-insecure-origin-as-secure</b></li>
                    <li>Enable the flag and add your site URL</li>
                    <li>Relaunch Chrome</li>
                </ol>
            </div>`,
            showCloseButton: true,
            showConfirmButton: false,
            width: 600,
          });
          return false;
        }
        return true;
      }

      /**
       * Analyze content with Chrome's built-in AI
       */
      async analyzeContentWithAI(content) {
        try {
          const session = await window.ai.languageModel.create({
            systemPrompt: `You are an SEO expert. Analyze the provided content and give specific, actionable recommendations to improve SEO. Focus on:
            - Readability and clarity
            - Keyword usage and density
            - Content structure
            - Meta information
            - User engagement
            
            Provide recommendations in a clear, numbered list format.`
          });

          this.currentSession = session;

          const prompt = `Analyze this content for SEO and provide specific recommendations:\n\n${content}`;
          
          const stream = session.promptStreaming(prompt);
          let result = "";

          for await (const chunk of stream) {
            result += chunk;
            
            // Update modal with streaming results
            Swal.update({
              html: `<div class="seo-analysis-results">
                <h4>AI-Powered SEO Analysis</h4>
                <textarea class="result-content" disabled>${result}</textarea>
              </div>`,
              showCloseButton: true,
            });
          }

          return result;
        } catch (error) {
          throw error;
        }
      }

      /**
       * Generate SEO improvements with AI
       */
      async generateSEOImprovedContent(content, recommendations) {
        try {
          const session = await window.ai.languageModel.create({
            systemPrompt: `You are an SEO content optimizer. Rewrite the provided content to improve its SEO while maintaining the original meaning and tone. Apply the given recommendations.`
          });

          this.currentSession = session;

          const prompt = `Original content:\n${content}\n\nRecommendations:\n${recommendations}\n\nRewrite the content with SEO improvements:`;
          
          const stream = session.promptStreaming(prompt);
          let result = "";

          for await (const chunk of stream) {
            result += chunk;
            
            Swal.update({
              html: `<textarea class="result-content" disabled>${result}</textarea>`,
              showCloseButton: true,
            });
            
            const textarea = document.querySelector(".result-content");
            if (textarea) {
              textarea.scrollTop = textarea.scrollHeight;
            }
            Swal.showLoading();
          }

          return result;
        } catch (error) {
          throw error;
        }
      }

      /**
       * Show SEO Audit Modal
       */
      async showSEOAuditModal() {
        const content = await this.getContent();
        
        if (!content || content.trim() === "") {
          Swal.fire({
            html: `<div class="seo-audit-error"><p>Please enter some content first</p></div>`,
            showCloseButton: true,
            showConfirmButton: false,
          });
          return;
        }

        // First, show basic analysis from backend
        Swal.fire({
          title: "Analyzing Content...",
          html: `<div class="seo-audit-loading">
            <p>Running SEO audit...</p>
            <div class="spinner"></div>
          </div>`,
          showConfirmButton: false,
          allowOutsideClick: false,
        });

        // Call WordPress AJAX for basic analysis
        $.ajax({
          url: seoAuditData.ajaxUrl,
          type: 'POST',
          data: {
            action: 'seo_audit_analyze_content',
            nonce: seoAuditData.nonce,
            content: content
          },
          success: (response) => {
            if (response.success) {
              this.showAnalysisResults(response.data, content);
            } else {
              Swal.fire('Error', response.data.message, 'error');
            }
          },
          error: () => {
            Swal.fire('Error', 'Failed to analyze content', 'error');
          }
        });
      }

      /**
       * Show analysis results with AI enhancement option
       */
      showAnalysisResults(results, content) {
        const readabilityScore = results.flesch_reading_ease?.score || 0;
        const readabilityGrade = results.readability_grade || 'F';
        const wordCount = results.word_count || 0;
        
        Swal.fire({
          title: "SEO Audit Results",
          html: `<div class="seo-audit-results">
            <div class="seo-score-card">
              <div class="score-badge score-${readabilityGrade.toLowerCase()}">${readabilityGrade}</div>
              <h4>Overall SEO Score</h4>
            </div>
            
            <div class="metrics-grid">
              <div class="metric">
                <span class="metric-label">Readability Score:</span>
                <span class="metric-value">${readabilityScore.toFixed(1)}/100</span>
              </div>
              <div class="metric">
                <span class="metric-label">Word Count:</span>
                <span class="metric-value">${wordCount} words</span>
              </div>
              <div class="metric">
                <span class="metric-label">Grade Level:</span>
                <span class="metric-value">${results.flesch_kincaid_grade?.grade || 'N/A'}</span>
              </div>
            </div>

            <div class="issues-section">
              <h5>Issues Found:</h5>
              <ul class="issues-list">
                ${(results.issues || []).map(issue => `
                  <li class="issue-${issue.severity}">
                    <strong>${issue.type}:</strong> ${issue.message}
                  </li>
                `).join('')}
              </ul>
            </div>

            <div class="recommendations-section">
              <h5>Recommendations:</h5>
              <ul class="recommendations-list">
                ${(results.recommendations || []).map(rec => `
                  <li>${rec}</li>
                `).join('')}
              </ul>
            </div>
          </div>`,
          footer: `<div class="seo-audit-actions">
            <button class="seo-btn seo-btn-ai" id="ai-enhance-btn">
              <i class="eicon-ai"></i> Enhance with AI
            </button>
            <button class="seo-btn seo-btn-secondary" id="export-report-btn">
              Export Report
            </button>
          </div>`,
          showCloseButton: true,
          showConfirmButton: false,
          width: 700,
          customClass: {
            container: "seo-audit-modal",
          },
          didOpen: () => {
            // AI Enhancement button
            document.getElementById("ai-enhance-btn")?.addEventListener("click", async () => {
              await this.enhanceWithAI(content, results);
            });

            // Export report button
            document.getElementById("export-report-btn")?.addEventListener("click", () => {
              this.exportReport(results);
            });
          },
          willClose: () => {
            if (this.currentSession) {
              try {
                this.currentSession.destroy();
                this.currentSession = null;
              } catch (error) {
                console.error("Error destroying AI session:", error);
              }
            }
          },
        });
      }

      /**
       * Enhance content with AI
       */
      async enhanceWithAI(content, results) {
        if (!this.checkBrowser()) return;
        if (!this.checkChromeVersion(128)) return;
        if (!this.checkSecureContext()) return;

        // Check AI availability
        if (typeof window.ai === 'undefined' || !window.ai.languageModel) {
          Swal.fire({
            title: "AI Model Unavailable",
            html: `<div class="seo-audit-error">
              <p><b>Chrome's built-in AI is not available.</b></p>
              <ol>
                <li>Update Chrome to version 128+</li>
                <li>Enable: <code>chrome://flags/#optimization-guide-on-device-model</code></li>
                <li>Enable: <code>chrome://flags/#prompt-api-for-gemini-nano</code></li>
                <li>Relaunch Chrome</li>
              </ol>
            </div>`,
            showCloseButton: true,
            showConfirmButton: false,
            width: 600,
          });
          return;
        }

        Swal.fire({
          title: "AI Enhancement",
          html: `<div class="ai-enhancement-loading">
            <p>Analyzing and generating SEO improvements...</p>
            <div class="spinner"></div>
          </div>`,
          showConfirmButton: false,
          allowOutsideClick: false,
        });

        try {
          const recommendations = results.recommendations?.join('\n') || '';
          const improvedContent = await this.analyzeContentWithAI(content);

          Swal.fire({
            title: "AI-Enhanced SEO Recommendations",
            html: `<textarea class="result-content" disabled>${improvedContent}</textarea>`,
            footer: `<div class="ai-footer">
              <button class="seo-btn seo-btn-primary" id="apply-improvements-btn">
                Apply Improvements
              </button>
              <button class="seo-btn seo-btn-secondary" id="copy-recommendations-btn">
                Copy to Clipboard
              </button>
            </div>`,
            showCloseButton: true,
            showConfirmButton: false,
            width: 700,
            didOpen: () => {
              // Apply improvements button
              document.getElementById("apply-improvements-btn")?.addEventListener("click", async () => {
                await this.applyImprovements(content, improvedContent);
              });

              // Copy to clipboard button
              document.getElementById("copy-recommendations-btn")?.addEventListener("click", () => {
                navigator.clipboard.writeText(improvedContent);
                Swal.fire({
                  toast: true,
                  position: 'top-end',
                  icon: 'success',
                  title: 'Copied to clipboard!',
                  showConfirmButton: false,
                  timer: 2000
                });
              });
            }
          });
        } catch (error) {
          Swal.fire('Error', 'Failed to generate AI recommendations: ' + error.message, 'error');
        }
      }

      /**
       * Apply AI improvements to editor
       */
      async applyImprovements(originalContent, improvedContent) {
        Swal.fire({
          title: "Apply AI Improvements?",
          html: `<p>This will replace your current content with the AI-improved version.</p>`,
          showCancelButton: true,
          confirmButtonText: 'Apply',
          cancelButtonText: 'Cancel'
        }).then(async (result) => {
          if (result.isConfirmed) {
            const formattedContent = improvedContent.trim().replace(/\r?\n/g, "<br />");
            
            if (window.parent.tinyMCE) {
              const editorId = this.editor.attr("id");
              const activeEditor = window.parent.tinyMCE.get(editorId);
              if (activeEditor) {
                activeEditor.setContent(formattedContent);
                activeEditor.fire("change");
              }
            } else {
              this.editor.val(formattedContent);
              this.editor.trigger("change");
            }

            Swal.fire({
              toast: true,
              position: 'top-end',
              icon: 'success',
              title: 'Content updated!',
              showConfirmButton: false,
              timer: 2000
            });
          }
        });
      }

      /**
       * Export audit report
       */
      exportReport(results) {
        const report = JSON.stringify(results, null, 2);
        const blob = new Blob([report], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `seo-audit-${Date.now()}.json`;
        a.click();
        URL.revokeObjectURL(url);

        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'success',
          title: 'Report exported!',
          showConfirmButton: false,
          timer: 2000
        });
      }

      /**
       * Main handler
       */
      async handleSEOAudit() {
        await this.showSEOAuditModal();
      }
    }

    // Initialize when Elementor is ready
    $(window).on("elementor/frontend/init", function () {
      elementor.channels.editor.on(
        "seo:audit:analyze",
        function (controlView) {
          const handler = new SEOAuditHandler(controlView);
          handler.handleSEOAudit();
        }
      );
    });

    // Global function for triggering from HTML
    window.triggerSEOAudit = function() {
      const handler = new SEOAuditHandler({});
      handler.handleSEOAudit();
    };

  })(jQuery);
}
