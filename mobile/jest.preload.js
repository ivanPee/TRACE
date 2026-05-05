/* eslint-env jest */

jest.mock('react-native/Libraries/Utilities/BackHandler', () => ({
  addEventListener: jest.fn(() => ({
    remove: jest.fn(),
  })),
  removeEventListener: jest.fn(),
  exitApp: jest.fn(),
}));
