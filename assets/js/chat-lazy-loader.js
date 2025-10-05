(function ($) {
  "use strict";

  class AIChatLazyLoader {
    constructor() {
      this.observer = null;
      this.init();
    }

    init() {
      if ("IntersectionObserver" in window) {
        this.observer = new IntersectionObserver(
          (entries) => this.handleIntersection(entries),
          {
            root: null,
            rootMargin: "50px",
            threshold: 0.01,
          }
        );
      }

      this.setupMutationObserver();
    }

    handleIntersection(entries) {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const img = entry.target;
          this.loadImage(img);
          this.observer.unobserve(img);
        }
      });
    }

    loadImage(img) {
      const $img = $(img);
      const src = $img.attr("data-src");
      const fullSrc = $img.attr("data-full-src");

      if (!src || $img.hasClass("loaded")) return;

      $img.css({
        opacity: "0",
        transition: "opacity 0.3s ease-in-out",
      });

      const tempImg = new Image();

      tempImg.onload = () => {
        $img.attr("src", src);
        $img.css("opacity", "1");
        $img.addClass("loaded");

        const $loader = $img.siblings(".image-loader");
        if ($loader.length) {
          $loader.fadeOut(300, function () {
            $(this).remove();
          });
        }

        if (fullSrc && fullSrc !== src) {
          this.loadFullImage($img, fullSrc);
        }
      };

      tempImg.onerror = () => {
        $img.attr(
          "src",
          'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="200" height="200"%3E%3Crect fill="%23ddd" width="200" height="200"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" dy=".3em" fill="%23999"%3EError%3C/text%3E%3C/svg%3E'
        );
        $img.css("opacity", "1");
        $img.addClass("error");

        const $loader = $img.siblings(".image-loader");
        if ($loader.length) {
          $loader.remove();
        }
      };

      tempImg.src = src;
    }

    loadFullImage($img, fullSrc) {
      const fullImg = new Image();

      fullImg.onload = () => {
        $img.css({
          transition: "opacity 0.3s ease-in-out",
          opacity: "0.7",
        });

        setTimeout(() => {
          $img.attr("src", fullSrc);
          $img.css("opacity", "1");
          $img.attr("data-loaded-full", "true");
        }, 50);
      };

      fullImg.src = fullSrc;
    }

    observe(element) {
      if (this.observer) {
        this.observer.observe(element);
      } else {
        this.loadImage(element);
      }
    }

    observeAll(container) {
      const $container = $(container || document);
      const $images = $container.find("img[data-src]:not(.loaded)");

      $images.each((index, img) => {
        this.observe(img);
      });
    }

    setupMutationObserver() {
      const chatContainer = document.querySelector(
        ".chat-messages, .ai-chat-messages, #chat-messages"
      );

      if (!chatContainer) return;

      const mutationObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          if (mutation.addedNodes.length) {
            mutation.addedNodes.forEach((node) => {
              if (node.nodeType === 1) {
                this.observeAll(node);
              }
            });
          }
        });
      });

      mutationObserver.observe(chatContainer, {
        childList: true,
        subtree: true,
      });
    }
  }

  $(document).ready(function () {
    window.AIChatLazyLoader = new AIChatLazyLoader();

    window.AIChatLazyLoader.observeAll();

    let scrollTimeout;
    const $chatContainer = $(
      ".chat-messages, .ai-chat-messages, #chat-messages"
    );

    if ($chatContainer.length) {
      $chatContainer.on("scroll", function () {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
          window.AIChatLazyLoader.observeAll();
        }, 100);
      });
    }

    $(window).on("resize", function () {
      clearTimeout(scrollTimeout);
      scrollTimeout = setTimeout(() => {
        window.AIChatLazyLoader.observeAll();
      }, 100);
    });
  });
})(jQuery);
